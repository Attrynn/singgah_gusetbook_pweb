<?php

namespace App\Services;

use App\Enums\CheckinMethod;
use App\Enums\GuestPartyStatus;
use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Enums\TokenType;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\GuestToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Event-day admission service.
 *
 * The admission unit is the invitation party (`guests` row), matching the
 * legacy contract: `checkins` is unique on (event_id, guest_id) and
 * `guests.checked_in_at` flags arrival. Milestone 5 adds individual pass
 * resolution on top of that unit: scanning an identified individual's guest
 * pass attributes the resulting check-in to that membership and pass, while a
 * legacy party-level QR token still works unchanged.
 */
class CheckinService
{
    public function __construct(
        protected InvitationService $invitations,
        protected AuditService $audit,
    ) {}

    /**
     * @return array{status:string,message:string,guest:?Guest,checkin:?Checkin,warning:?string}
     */
    public function scan(string $rawToken, User $officer, ?Event $expectedEvent = null): array
    {
        $rawToken = trim($rawToken);

        // Milestone 5: resolve an identified individual's guest pass first.
        $pass = GuestPass::findByRaw($rawToken);

        if ($pass) {
            return $this->scanIndividualPass($pass, $officer, $expectedEvent);
        }

        // Legacy party-level QR token path (unchanged behaviour).
        return $this->scanLegacyToken($rawToken, $officer, $expectedEvent);
    }

    /**
     * @return array{status:string,message:string,guest:?Guest,checkin:?Checkin,warning:?string}
     */
    protected function scanIndividualPass(GuestPass $pass, User $officer, ?Event $expectedEvent): array
    {
        if ($pass->type !== TokenType::Qr) {
            return $this->result('invalid', 'Pass ini bukan QR check-in.');
        }

        if ($pass->isRevoked()) {
            return $this->result('revoked', 'Guest pass ini telah dicabut dan tidak dapat digunakan.');
        }

        if ($pass->isExpired()) {
            return $this->result('expired', 'Guest pass sudah kedaluwarsa.');
        }

        $member = $pass->member;
        $guest = $member?->guest;
        $event = $pass->event ?? $guest?->event;

        if (! $member || ! $guest || ! $event) {
            return $this->result('invalid', 'Data pass tidak lengkap.');
        }

        if ($member->status !== GuestPartyStatus::Active) {
            return $this->result('inactive', 'Individu ini tidak aktif dan tidak dapat check-in.', $guest);
        }

        if ((int) $guest->event_id !== (int) $event->id) {
            return $this->result('invalid', 'Data pass tidak cocok dengan acara.');
        }

        if ($expectedEvent && $event->id !== $expectedEvent->id) {
            return $this->result('wrong_event', 'QR ini milik acara lain ('.$event->name.').', $guest);
        }

        if (! $event->allowsGuestActions()) {
            return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
        }

        if ($guest->status === GuestStatus::Blocked) {
            return $this->result('blocked', 'Tamu ini diblokir.', $guest);
        }

        if ($guest->status !== GuestStatus::Active) {
            return $this->result('inactive', 'Tamu berstatus non-aktif dan tidak dapat melakukan check-in.', $guest);
        }

        if ($guest->rsvp_status === RsvpStatus::Declined) {
            return $this->result('declined', 'Tamu telah menyatakan tidak hadir.', $guest);
        }

        $warning = $guest->rsvp_status === RsvpStatus::Confirmed
            ? null
            : 'Tamu belum mengonfirmasi kehadiran. Check-in tetap dicatat.';

        try {
            return DB::transaction(function () use ($pass, $member, $guest, $event, $officer, $warning, $expectedEvent) {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->first();
                $lockedGuest = Guest::whereKey($guest->id)->lockForUpdate()->first();
                $freshPass = GuestPass::whereKey($pass->id)->lockForUpdate()->first();
                $freshMember = GuestPartyMembership::whereKey($member->id)->lockForUpdate()->first();

                if (! $lockedEvent || ! $lockedEvent->allowsGuestActions()) {
                    return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
                }

                if ($expectedEvent && $lockedEvent->id !== $expectedEvent->id) {
                    return $this->result('wrong_event', 'QR ini milik acara lain ('.$lockedEvent->name.').', $guest);
                }

                if (! $lockedGuest || $lockedGuest->trashed()) {
                    return $this->result('invalid', 'Data tamu tidak ditemukan atau telah dihapus.', $guest);
                }

                if ($lockedGuest->status === GuestStatus::Blocked) {
                    return $this->result('blocked', 'Tamu ini diblokir.', $lockedGuest);
                }

                if ($lockedGuest->status !== GuestStatus::Active) {
                    return $this->result('inactive', 'Tamu berstatus non-aktif dan tidak dapat melakukan check-in.', $lockedGuest);
                }

                if ((int) $lockedGuest->event_id !== (int) $lockedEvent->id) {
                    return $this->result('invalid', 'Data tamu tidak cocok dengan acara ini.', $lockedGuest);
                }

                if (! $freshMember || $freshMember->status !== GuestPartyStatus::Active) {
                    return $this->result('inactive', 'Individu ini tidak aktif dan tidak dapat check-in.', $lockedGuest);
                }

                if (! $freshPass || $freshPass->isRevoked()) {
                    return $this->result('revoked', 'Guest pass ini telah dicabut dan tidak dapat digunakan.', $lockedGuest);
                }

                if ($freshPass->isExpired()) {
                    return $this->result('expired', 'Guest pass sudah kedaluwarsa.', $lockedGuest);
                }

                if ($lockedGuest->checked_in_at !== null || Checkin::where('guest_id', $lockedGuest->id)->exists()) {
                    return $this->result(
                        'duplicate',
                        'Tamu ini sudah check-in pada '.$lockedGuest->checked_in_at?->translatedFormat('d M Y H:i').'.',
                        $lockedGuest,
                        $lockedGuest->checkin
                    );
                }

                $checkin = Checkin::create([
                    'event_id' => $lockedEvent->id,
                    'guest_id' => $lockedGuest->id,
                    'guest_party_membership_id' => $freshMember->id,
                    'guest_pass_id' => $freshPass->id,
                    'checked_in_by' => $officer->id,
                    'method' => CheckinMethod::Qr,
                    'checked_in_at' => now(),
                    'ip_address' => request()?->ip(),
                ]);

                $lockedGuest->forceFill(['checked_in_at' => now()])->save();
                $freshPass->markUsed();

                $this->audit->recordCritical(
                    'checkin.qr',
                    "Check-in QR: {$lockedGuest->name} ({$freshMember->name})",
                    $lockedEvent,
                    $officer,
                    $checkin,
                    ['guest_id' => $lockedGuest->id, 'membership_id' => $freshMember->id, 'pass_id' => $freshPass->id]
                );
                $this->audit->activity($lockedEvent, 'checkin', "{$freshMember->name} check-in via QR", $lockedGuest, $officer);

                return $this->result('success', 'Check-in berhasil.', $lockedGuest, $checkin, $warning);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return $this->result('duplicate', 'Tamu ini sudah check-in.', $guest, $guest->checkin);
            }

            throw $e;
        }
    }

    /**
     * Legacy party-level QR token resolution. Kept behaviour-compatible with
     * Milestone 1–4 so existing QR passes continue to work at the desk.
     *
     * @return array{status:string,message:string,guest:?Guest,checkin:?Checkin,warning:?string}
     */
    protected function scanLegacyToken(string $rawToken, User $officer, ?Event $expectedEvent): array
    {
        $token = GuestToken::findByRaw($rawToken);

        if (! $token || $token->type !== TokenType::Qr) {
            return $this->result('invalid', 'QR tidak dikenali. Pastikan QR berasal dari VOWSPACE.');
        }

        if ($token->isRevoked()) {
            return $this->result('revoked', 'QR ini telah dicabut dan tidak dapat digunakan.');
        }

        if ($token->isExpired()) {
            return $this->result('expired', 'QR sudah kedaluwarsa.');
        }

        $guest = $token->guest;
        $event = $token->event;

        if (! $guest || ! $event || $guest->event_id !== $event->id) {
            return $this->result('invalid', 'Data tamu tidak cocok dengan QR.');
        }

        if ($expectedEvent && $event->id !== $expectedEvent->id) {
            return $this->result('wrong_event', 'QR ini milik acara lain ('.$event->name.').', $guest);
        }

        if (! $event->allowsGuestActions()) {
            return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
        }

        if ($guest->status === GuestStatus::Blocked) {
            return $this->result('blocked', 'Tamu ini diblokir.', $guest);
        }

        if ($guest->status !== GuestStatus::Active) {
            return $this->result('inactive', 'Tamu berstatus non-aktif dan tidak dapat melakukan check-in.', $guest);
        }

        if ($guest->rsvp_status === RsvpStatus::Declined) {
            return $this->result('declined', 'Tamu telah menyatakan tidak hadir.', $guest);
        }

        $warning = $guest->rsvp_status === RsvpStatus::Confirmed
            ? null
            : 'Tamu belum mengonfirmasi kehadiran. Check-in tetap dicatat.';

        try {
            return DB::transaction(function () use ($guest, $event, $officer, $token, $warning, $expectedEvent) {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->first();
                $lockedGuest = Guest::whereKey($guest->id)->lockForUpdate()->first();

                if (! $lockedEvent || ! $lockedEvent->allowsGuestActions()) {
                    return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
                }

                if ($expectedEvent && $lockedEvent->id !== $expectedEvent->id) {
                    return $this->result('wrong_event', 'QR ini milik acara lain ('.$lockedEvent->name.').', $guest);
                }

                if (! $lockedGuest || $lockedGuest->trashed()) {
                    return $this->result('invalid', 'Data tamu tidak ditemukan atau telah dihapus.', $guest);
                }

                if ($lockedGuest->status === GuestStatus::Blocked) {
                    return $this->result('blocked', 'Tamu ini diblokir.', $lockedGuest);
                }

                if ($lockedGuest->status !== GuestStatus::Active) {
                    return $this->result('inactive', 'Tamu berstatus non-aktif dan tidak dapat melakukan check-in.', $lockedGuest);
                }

                if ($lockedGuest->event_id !== $lockedEvent->id) {
                    return $this->result('invalid', 'Data tamu tidak cocok dengan acara ini.', $lockedGuest);
                }

                $freshToken = $token->fresh();
                if (! $freshToken || $freshToken->isRevoked()) {
                    return $this->result('revoked', 'QR ini telah dicabut dan tidak dapat digunakan.');
                }
                if ($freshToken->isExpired()) {
                    return $this->result('expired', 'QR sudah kedaluwarsa.');
                }

                if ($lockedGuest->checked_in_at !== null || Checkin::where('guest_id', $lockedGuest->id)->exists()) {
                    return $this->result(
                        'duplicate',
                        'Tamu ini sudah check-in pada '.$lockedGuest->checked_in_at?->translatedFormat('d M Y H:i').'.',
                        $lockedGuest,
                        $lockedGuest->checkin
                    );
                }

                $checkin = Checkin::create([
                    'event_id' => $lockedEvent->id,
                    'guest_id' => $lockedGuest->id,
                    'checked_in_by' => $officer->id,
                    'method' => CheckinMethod::Qr,
                    'checked_in_at' => now(),
                    'ip_address' => request()?->ip(),
                ]);

                $lockedGuest->forceFill(['checked_in_at' => now()])->save();
                $freshToken->markUsed();

                $this->audit->recordCritical(
                    'checkin.qr',
                    "Check-in QR: {$lockedGuest->name}",
                    $lockedEvent,
                    $officer,
                    $checkin,
                    ['guest_id' => $lockedGuest->id]
                );
                $this->audit->activity($lockedEvent, 'checkin', "{$lockedGuest->name} check-in via QR", $lockedGuest, $officer);

                return $this->result('success', 'Check-in berhasil.', $lockedGuest, $checkin, $warning);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return $this->result('duplicate', 'Tamu ini sudah check-in.', $guest, $guest->checkin);
            }

            throw $e;
        }
    }

    public function manual(Guest $guest, User $officer, ?string $notes = null): array
    {
        $guest->loadMissing('event');
        $event = $guest->event;

        if (! $event || ! $event->allowsGuestActions()) {
            return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
        }

        if ($guest->status === GuestStatus::Blocked) {
            return $this->result('blocked', 'Tamu ini diblokir.', $guest);
        }

        if ($guest->status !== GuestStatus::Active) {
            return $this->result('inactive', 'Tamu berstatus non-aktif dan tidak dapat melakukan check-in.', $guest);
        }

        try {
            return DB::transaction(function () use ($guest, $event, $officer, $notes) {
                $lockedEvent = Event::whereKey($event->id)->lockForUpdate()->first();
                $lockedGuest = Guest::whereKey($guest->id)->lockForUpdate()->first();

                if (! $lockedEvent || ! $lockedEvent->allowsGuestActions()) {
                    return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
                }

                if (! $lockedGuest || $lockedGuest->trashed()) {
                    return $this->result('invalid', 'Data tamu tidak ditemukan atau telah dihapus.', $guest);
                }

                if ($lockedGuest->status === GuestStatus::Blocked) {
                    return $this->result('blocked', 'Tamu ini diblokir.', $lockedGuest);
                }

                if ($lockedGuest->status !== GuestStatus::Active) {
                    return $this->result('inactive', 'Tamu berstatus non-aktif dan tidak dapat melakukan check-in.', $lockedGuest);
                }

                if ($lockedGuest->event_id !== $lockedEvent->id) {
                    return $this->result('invalid', 'Data tamu tidak cocok dengan acara ini.', $lockedGuest);
                }

                if ($lockedGuest->checked_in_at !== null || Checkin::where('guest_id', $lockedGuest->id)->exists()) {
                    return $this->result('duplicate', 'Tamu ini sudah check-in.', $lockedGuest, $lockedGuest->checkin);
                }

                $checkin = Checkin::create([
                    'event_id' => $lockedEvent->id,
                    'guest_id' => $lockedGuest->id,
                    'checked_in_by' => $officer->id,
                    'method' => CheckinMethod::Manual,
                    'checked_in_at' => now(),
                    'notes' => $notes,
                    'ip_address' => request()?->ip(),
                ]);

                $lockedGuest->forceFill(['checked_in_at' => now()])->save();

                $this->audit->recordCritical(
                    'checkin.manual',
                    "Check-in manual: {$lockedGuest->name}",
                    $lockedEvent,
                    $officer,
                    $checkin,
                    ['guest_id' => $lockedGuest->id]
                );
                $this->audit->activity($lockedEvent, 'checkin', "{$lockedGuest->name} check-in manual", $lockedGuest, $officer);

                return $this->result('success', 'Check-in manual berhasil.', $lockedGuest, $checkin);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicate($e)) {
                return $this->result('duplicate', 'Tamu ini sudah check-in.', $guest, $guest->checkin);
            }

            throw $e;
        }
    }

    protected function isDuplicate(QueryException $e): bool
    {
        return $e instanceof UniqueConstraintViolationException
            || (int) $e->getCode() === 23000;
    }

    /**
     * @return array{status:string,message:string,guest:?Guest,checkin:?Checkin,warning:?string}
     */
    protected function result(
        string $status,
        string $message,
        ?Guest $guest = null,
        ?Checkin $checkin = null,
        ?string $warning = null,
    ): array {
        return compact('status', 'message', 'guest', 'checkin', 'warning');
    }
}
