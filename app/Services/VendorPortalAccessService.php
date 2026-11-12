<?php

namespace App\Services;

use App\Enums\VendorPortalStatus;
use App\Models\EventVendorEngagement;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\VendorPortalEngagementAccess;
use App\Models\VendorPortalMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Engagement-scoped authorization for the optional external vendor portal.
 *
 * A vendor portal membership on its own grants nothing: every engagement
 * resource requires an explicit, unrevoked access grant for that engagement.
 * Invitation tokens are stored hashed and are single-use.
 */
class VendorPortalAccessService
{
    public const INVITATION_TTL_HOURS = 168;

    public function membershipFor(User $user, int $vendorId): ?VendorPortalMembership
    {
        return VendorPortalMembership::query()
            ->where('user_id', $user->id)
            ->where('vendor_id', $vendorId)
            ->where('status', VendorPortalStatus::Active)
            ->whereNotNull('accepted_at')
            ->first();
    }

    public function findPendingInvitation(string $token): ?VendorPortalMembership
    {
        return VendorPortalMembership::query()
            ->where('invitation_token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * @return Collection<int, VendorPortalMembership>
     */
    public function membershipsFor(User $user): Collection
    {
        return VendorPortalMembership::query()
            ->with(['vendor', 'vendorContact'])
            ->where('user_id', $user->id)
            ->where('status', VendorPortalStatus::Active)
            ->whereNotNull('accepted_at')
            ->get();
    }

    /**
     * Engagements a portal user may open. Used for the portal index and for
     * every per-request authorization check.
     */
    public function accessibleEngagements(User $user): Builder
    {
        $vendorIds = $this->membershipsFor($user)->pluck('vendor_id')->all();

        return EventVendorEngagement::query()
            ->whereIn('vendor_id', $vendorIds)
            ->whereHas('portalAccess', function (Builder $query) use ($user): void {
                $query->whereNull('revoked_at')
                    ->whereHas('membership', function (Builder $membership) use ($user): void {
                        $membership->where('user_id', $user->id)
                            ->where('status', VendorPortalStatus::Active);
                    });
            });
    }

    public function canAccessEngagement(User $user, EventVendorEngagement $engagement): bool
    {
        $membership = $this->membershipFor($user, (int) $engagement->vendor_id);

        if ($membership === null) {
            return false;
        }

        return $this->accessIsActive($membership, $engagement);
    }

    /**
     * Abort with 404 (not 403) so portal users cannot enumerate engagement ids.
     */
    public function authorizeEngagement(User $user, EventVendorEngagement $engagement): VendorPortalMembership
    {
        $membership = $this->membershipFor($user, (int) $engagement->vendor_id);

        abort_unless($membership !== null && $this->accessIsActive($membership, $engagement), 404);

        return $membership;
    }

    protected function accessIsActive(VendorPortalMembership $membership, EventVendorEngagement $engagement): bool
    {
        return VendorPortalEngagementAccess::query()
            ->where('vendor_portal_membership_id', $membership->id)
            ->where('event_vendor_engagement_id', $engagement->id)
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * @return array{0: VendorPortalMembership, 1: string} membership and raw token
     */
    public function invite(
        Vendor $vendor,
        ?VendorContact $contact,
        string $email,
        User $actor,
    ): array {
        if ($contact !== null && (int) $contact->vendor_id !== (int) $vendor->id) {
            throw ValidationException::withMessages([
                'vendor_contact_id' => 'Kontak vendor tidak termasuk vendor ini.',
            ]);
        }

        $email = Str::lower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Alamat email undangan tidak valid.']);
        }

        $token = Str::random(64);

        $membership = DB::transaction(function () use ($vendor, $contact, $email, $actor, $token): VendorPortalMembership {
            $membership = VendorPortalMembership::query()
                ->where('vendor_id', $vendor->id)
                ->where('invited_email', $email)
                ->where('status', VendorPortalStatus::Invited)
                ->first();

            $attributes = [
                'organization_id' => $vendor->organization_id,
                'vendor_contact_id' => $contact?->id,
                'invited_email' => $email,
                'status' => VendorPortalStatus::Invited,
                'invitation_token_hash' => hash('sha256', $token),
                'invitation_expires_at' => now()->addHours(self::INVITATION_TTL_HOURS),
                'invited_by' => $actor->id,
                'accepted_at' => null,
                'revoked_at' => null,
                'revoked_by' => null,
            ];

            if ($membership !== null) {
                $membership->update($attributes);

                return $membership;
            }

            return VendorPortalMembership::create(array_merge($attributes, [
                'vendor_id' => $vendor->id,
                'user_id' => null,
            ]));
        });

        return [$membership, $token];
    }

    /**
     * Accept an invitation. The authenticated user's verified email must match
     * the invited address; expired or revoked invitations never grant access.
     */
    public function accept(string $token, User $user): VendorPortalMembership
    {
        $membership = VendorPortalMembership::query()
            ->where('invitation_token_hash', hash('sha256', $token))
            ->first();

        abort_unless($membership !== null, 404);

        if ($membership->status === VendorPortalStatus::Revoked) {
            abort(403, 'Undangan ini sudah dicabut.');
        }

        if ($membership->status === VendorPortalStatus::Expired) {
            abort(410, 'Undangan ini sudah kedaluwarsa.');
        }

        if ($membership->status !== VendorPortalStatus::Invited) {
            abort(409, 'Undangan ini sudah digunakan.');
        }

        if ($membership->invitation_expires_at !== null && $membership->invitation_expires_at->isPast()) {
            $membership->update([
                'status' => VendorPortalStatus::Expired,
                'invitation_token_hash' => null,
            ]);

            abort(410, 'Undangan ini sudah kedaluwarsa.');
        }

        $invited = Str::lower((string) $membership->invited_email);

        if ($invited === '' || Str::lower((string) $user->email) !== $invited) {
            abort(403, 'Undangan ini ditujukan untuk alamat email lain.');
        }

        $conflict = VendorPortalMembership::query()
            ->where('vendor_id', $membership->vendor_id)
            ->where('user_id', $user->id)
            ->whereKeyNot($membership->getKey())
            ->first();

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'email' => 'Akun ini sudah memiliki akses portal untuk vendor tersebut.',
            ]);
        }

        $membership->forceFill([
            'user_id' => $user->id,
            'status' => VendorPortalStatus::Active,
            'accepted_at' => now(),
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
        ])->save();

        return $membership;
    }

    public function grant(
        VendorPortalMembership $membership,
        EventVendorEngagement $engagement,
        User $actor,
    ): VendorPortalEngagementAccess {
        if ((int) $membership->vendor_id !== (int) $engagement->vendor_id) {
            throw ValidationException::withMessages([
                'engagement' => 'Engagement ini bukan milik vendor yang diundang.',
            ]);
        }

        if ($membership->status !== VendorPortalStatus::Active) {
            throw ValidationException::withMessages([
                'membership' => 'Akses portal vendor belum aktif.',
            ]);
        }

        return VendorPortalEngagementAccess::updateOrCreate(
            [
                'vendor_portal_membership_id' => $membership->id,
                'event_vendor_engagement_id' => $engagement->id,
            ],
            [
                'granted_at' => now(),
                'granted_by' => $actor->id,
                'revoked_at' => null,
            ],
        );
    }

    public function revokeAccess(
        VendorPortalMembership $membership,
        EventVendorEngagement $engagement,
    ): void {
        VendorPortalEngagementAccess::query()
            ->where('vendor_portal_membership_id', $membership->id)
            ->where('event_vendor_engagement_id', $engagement->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Revoking a membership removes future authorization immediately while the
     * historical membership row and prior vendor contributions are preserved.
     */
    public function revokeMembership(VendorPortalMembership $membership, User $actor): void
    {
        DB::transaction(function () use ($membership, $actor): void {
            $membership->forceFill([
                'status' => VendorPortalStatus::Revoked,
                'revoked_at' => now(),
                'revoked_by' => $actor->id,
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
            ])->save();

            VendorPortalEngagementAccess::query()
                ->where('vendor_portal_membership_id', $membership->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });
    }

    /**
     * Expire stale invitations so a dormant token can never be accepted later.
     */
    public function expireStaleInvitations(): int
    {
        return VendorPortalMembership::query()
            ->where('status', VendorPortalStatus::Invited)
            ->whereNotNull('invitation_expires_at')
            ->where('invitation_expires_at', '<', now())
            ->update([
                'status' => VendorPortalStatus::Expired,
                'invitation_token_hash' => null,
            ]);
    }
}
