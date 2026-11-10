<?php

namespace App\Services;

use App\Enums\TokenType;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Foundation service for individual-level guest pass identity.
 *
 * Each `GuestPartyMembership` is an identified individual belonging to an
 * invitation party (`Guest`). Each `GuestPass` binds that individual to an
 * event scope with a cryptographic credential. Never fabricates identities.
 */
class GuestPassService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * Issue an admission pass for an identified individual guest.
     *
     * Revokes any prior active pass of the same type before issuing so that one
     * individual cannot carry multiple active credentials of the same kind.
     */
    public function issue(GuestPartyMembership $member, TokenType $type = TokenType::Invitation, ?User $actor = null): GuestPass
    {
        return DB::transaction(function () use ($member, $type, $actor): GuestPass {
            $member->loadMissing(['guest.event']);
            $event = $member->guest?->event ?? $member->event;

            if (! $event) {
                throw new \InvalidArgumentException('Member does not belong to a valid event.');
            }

            // Revoke prior active passes of this type for the member.
            GuestPass::where('guest_party_membership_id', $member->id)
                ->where('type', $type->value)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'status' => 'revoked',
                ]);

            $rawToken = GuestPass::generateRawToken();

            $pass = GuestPass::create([
                'guest_party_membership_id' => $member->id,
                'event_id' => $event->id,
                'type' => $type,
                'token_hash' => GuestPass::hashToken($rawToken),
                'token_ciphertext' => Crypt::encryptString($rawToken),
                'token_prefix' => substr($rawToken, 0, 8),
                'expires_at' => $this->calculateExpiresAt($event, $type),
                'revoked_at' => null,
                'status' => 'active',
                'created_by' => $actor?->id,
            ]);

            $this->audit->record(
                'guest.pass.issued',
                "Guest pass diterbitkan untuk individu: {$member->name} ({$type->value})",
                $event,
                $actor,
                $pass,
            );

            return $pass->fresh();
        });
    }

    /**
     * Revoke a guest pass.
     */
    public function revoke(GuestPass $pass, ?User $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($pass, $actor, $reason): void {
            $pass->forceFill([
                'revoked_at' => now(),
                'status' => 'revoked',
            ])->save();

            $description = "Guest pass dicabut untuk individu: {$pass->member?->name}";
            if ($reason) {
                $description .= " (Alasan: {$reason})";
            }

            $this->audit->record(
                'guest.pass.revoked',
                $description,
                $pass->event,
                $actor,
                $pass,
                ['reason' => $reason],
            );
        });
    }

    /**
     * Resolve a raw pass token into an active, valid GuestPass.
     *
     * Returns null if the token is unknown, wrong type, expired, revoked, or the
     * owning event no longer exists. The raw ciphertext is never decrypted here.
     */
    public function resolve(string $rawToken, TokenType $type = TokenType::Invitation): ?GuestPass
    {
        $hash = GuestPass::hashToken($rawToken);

        $pass = GuestPass::with(['member.guest.event', 'event'])
            ->where('token_hash', $hash)
            ->first();

        if (! $pass || $pass->type !== $type) {
            return null;
        }

        if (! $pass->isValid()) {
            return null;
        }

        if (! $pass->member || ! $pass->event || $pass->event->trashed()) {
            return null;
        }

        return $pass;
    }

    /**
     * Get the active pass of a given type for an individual member.
     */
    public function activePassFor(GuestPartyMembership $member, TokenType $type = TokenType::Invitation): ?GuestPass
    {
        return GuestPass::where('guest_party_membership_id', $member->id)
            ->where('type', $type->value)
            ->whereNull('revoked_at')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /**
     * Get all active passes for an individual member.
     */
    public function activePassesForMember(GuestPartyMembership $member): Collection
    {
        return GuestPass::where('guest_party_membership_id', $member->id)
            ->whereNull('revoked_at')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get();
    }

    /**
     * Get all active passes for every member in an invitation party.
     */
    public function activePassesForParty(Guest $party): Collection
    {
        $memberIds = $party->members()->pluck('id');

        return GuestPass::whereIn('guest_party_membership_id', $memberIds)
            ->whereNull('revoked_at')
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with('member')
            ->get();
    }

    /**
     * Expiration calculation aligned with event bounds and configured TTLs.
     */
    protected function calculateExpiresAt(Event $event, TokenType $type): ?Carbon
    {
        if ($type === TokenType::Qr) {
            $eventEnd = $event->endsAtUtc();
            if ($eventEnd) {
                return Carbon::instance($eventEnd)->addDays((int) config('vowspace.tokens.qr_grace_days', 3));
            }
            $ttl = (int) config('vowspace.tokens.qr_ttl_days', 30);

            return $ttl ? now()->addDays($ttl) : null;
        }

        $ttl = (int) config('vowspace.tokens.invitation_ttl_days');

        return $ttl ? now()->addDays($ttl) : now()->addYears(1);
    }
}
