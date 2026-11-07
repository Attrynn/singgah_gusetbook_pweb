<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Enums\TokenType;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestToken;
use App\Models\Invitation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvitationService
{
    public function issueForGuest(Guest $guest): Invitation
    {
        return DB::transaction(function () use ($guest) {
            $guest->loadMissing('event');

            $guest->tokens()
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitationToken = $this->createToken($guest, TokenType::Invitation);
            $qrToken = $this->createToken($guest, TokenType::Qr);

            $invitation = Invitation::updateOrCreate(
                ['guest_id' => $guest->id],
                [
                    'event_id' => $guest->event_id,
                    'guest_token_id' => $invitationToken->id,
                    'status' => InvitationStatus::NotSent,
                    'template' => 'classic',
                    'open_count' => 0,
                    'opened_at' => null,
                    'sent_at' => null,
                ]
            );

            return $invitation->fresh(['token', 'guest.event']);
        });
    }

    protected function createToken(Guest $guest, TokenType $type): GuestToken
    {
        $raw = Str::random(64);

        return GuestToken::create([
            'guest_id' => $guest->id,
            'event_id' => $guest->event_id,
            'type' => $type,
            'token_hash' => GuestToken::hashToken($raw),
            'token_ciphertext' => Crypt::encryptString($raw),
            'token_prefix' => substr($raw, 0, 8),
            'expires_at' => $type === TokenType::Qr
                ? $this->qrExpiresAt($guest)
                : $this->invitationExpiresAt(),
        ]);
    }

    protected function invitationExpiresAt(): ?Carbon
    {
        $ttl = config('vowspace.tokens.invitation_ttl_days');

        return $ttl ? now()->addDays((int) $ttl) : null;
    }

    /**
     * A QR pass is valid for the shorter of the configured rollover TTL and
     * the event end plus a grace period. Event lifecycle therefore bounds the
     * token, and a past event cannot mint a permanently valid pass.
     */
    public function qrExpiresAt(Guest $guest): ?Carbon
    {
        $guest->loadMissing('event');

        $ttl = config('vowspace.tokens.qr_ttl_days');
        $expiresAt = $ttl ? now()->addDays((int) $ttl) : null;

        $eventEnd = $guest->event?->endsAtUtc();

        if ($eventEnd) {
            $limit = Carbon::instance($eventEnd)
                ->addDays((int) config('vowspace.tokens.qr_grace_days', 3));

            $expiresAt = $expiresAt && $expiresAt->lessThan($limit) ? $expiresAt : $limit;
        }

        return $expiresAt;
    }

    public function rawToken(Guest $guest, TokenType $type = TokenType::Invitation): ?string
    {
        $token = $type === TokenType::Invitation
            ? $guest->activeInvitationToken()
            : $guest->activeQrToken();

        return $token?->rawToken();
    }

    public function ensureQrToken(Guest $guest): string
    {
        $token = $guest->activeQrToken();

        if ($token) {
            return $token->rawToken();
        }

        try {
            return $this->createToken($guest, TokenType::Qr)->rawToken();
        } catch (UniqueConstraintViolationException) {
            return $guest->fresh()->activeQrToken()?->rawToken()
                ?? throw new \RuntimeException('Failed to resolve QR token after race condition.');
        }
    }

    public function invitationUrl(Guest $guest): ?string
    {
        $raw = $this->rawToken($guest, TokenType::Invitation);

        return $raw ? route('guest.invitation', $raw) : null;
    }

    public function qrUrl(Guest $guest): ?string
    {
        $raw = $this->rawToken($guest, TokenType::Qr);

        return $raw ? route('guest.pass', $raw) : null;
    }

    public function whatsappUrl(Guest $guest): ?string
    {
        $url = $this->invitationUrl($guest);

        if (! $url) {
            return null;
        }

        $event = $guest->event;
        $message = "Kepada {$guest->name},\n\n"
            .'Dengan penuh kebahagiaan, kami mengundang Anda untuk hadir di pernikahan '
            ."{$event->coupleNames()}.\n\n"
            .'Hari: '.$event->event_date?->translatedFormat('l, d F Y')."\n"
            ."Lokasi: {$event->venue_name}\n\n"
            ."Buka undangan personal Anda di sini:\n{$url}\n\n"
            ."Merupakan kebahagiaan bagi kami apabila Anda berkenan hadir.\n\n"
            ."Salam hangat,\n{$event->coupleNames()}";

        $phone = preg_replace('/[^0-9]/', '', (string) $guest->phone);

        if ($phone && str_starts_with($phone, '0')) {
            $phone = '62'.substr($phone, 1);
        }

        $base = $phone ? "https://wa.me/{$phone}" : 'https://wa.me/';

        return $base.'?text='.rawurlencode($message);
    }

    public function resolveInvitation(string $raw): ?GuestToken
    {
        return $this->resolve($raw, TokenType::Invitation);
    }

    public function resolveQr(string $raw): ?GuestToken
    {
        return $this->resolve($raw, TokenType::Qr);
    }

    protected function resolve(string $raw, TokenType $type): ?GuestToken
    {
        $token = GuestToken::findByRaw($raw);

        if (! $token || $token->type !== $type) {
            return null;
        }

        if (! $token->isValid()) {
            return null;
        }

        if (! $token->guest || ! $token->event) {
            return null;
        }

        if ($token->event->trashed()) {
            return null;
        }

        return $token;
    }

    public function registerOpen(Guest $guest): void
    {
        $invitation = $guest->invitation;

        if ($invitation) {
            $invitation->registerOpen();
        }
    }

    public function markShared(Guest $guest): void
    {
        $invitation = $guest->invitation;

        if ($invitation) {
            $invitation->forceFill([
                'status' => $invitation->status === InvitationStatus::NotSent
                    ? InvitationStatus::SharedManually
                    : $invitation->status,
                'last_shared_at' => now(),
            ])->save();
        }
    }

    public function eventOf(GuestToken $token): ?Event
    {
        return $token->event;
    }
}
