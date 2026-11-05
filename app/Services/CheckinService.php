<?php

namespace App\Services;

use App\Enums\CheckinMethod;
use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Enums\TokenType;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CheckinService
{
    public function __construct(
        protected InvitationService $invitations,
        protected AuditService $audit,
    ) {
    }

    /**
     * @return array{status:string,message:string,guest:?Guest,checkin:?Checkin,warning:?string}
     */
    public function scan(string $rawToken, User $officer, ?Event $expectedEvent = null): array
    {
        $token = GuestToken::findByRaw(trim($rawToken));

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

        if ($guest->rsvp_status === RsvpStatus::Declined) {
            return $this->result('declined', 'Tamu telah menyatakan tidak hadir.', $guest);
        }

        $warning = $guest->rsvp_status === RsvpStatus::Confirmed
            ? null
            : 'Tamu belum mengonfirmasi kehadiran. Check-in tetap dicatat.';

        try {
            return DB::transaction(function () use ($guest, $event, $officer, $token, $warning) {
                $lockedGuest = Guest::whereKey($guest->id)->lockForUpdate()->first();

                if ($lockedGuest->checked_in_at !== null || Checkin::where('guest_id', $lockedGuest->id)->exists()) {
                    return $this->result(
                        'duplicate',
                        'Tamu ini sudah check-in pada '.$lockedGuest->checked_in_at?->translatedFormat('d M Y H:i').'.',
                        $lockedGuest,
                        $lockedGuest->checkin
                    );
                }

                $checkin = Checkin::create([
                    'event_id' => $event->id,
                    'guest_id' => $lockedGuest->id,
                    'checked_in_by' => $officer->id,
                    'method' => CheckinMethod::Qr,
                    'checked_in_at' => now(),
                    'ip_address' => request()?->ip(),
                ]);

                $lockedGuest->forceFill(['checked_in_at' => now()])->save();
                $token->markUsed();

                $this->audit->record(
                    'checkin.qr',
                    "Check-in QR: {$lockedGuest->name}",
                    $event,
                    $officer,
                    $checkin,
                    ['guest_id' => $lockedGuest->id]
                );
                $this->audit->activity($event, 'checkin', "{$lockedGuest->name} check-in via QR", $lockedGuest, $officer);

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

        if (! $event->allowsGuestActions()) {
            return $this->result('inactive', 'Acara belum aktif atau sudah selesai.', $guest);
        }

        if ($guest->status === GuestStatus::Blocked) {
            return $this->result('blocked', 'Tamu ini diblokir.', $guest);
        }

        try {
            return DB::transaction(function () use ($guest, $event, $officer, $notes) {
                $lockedGuest = Guest::whereKey($guest->id)->lockForUpdate()->first();

                if ($lockedGuest->checked_in_at !== null || Checkin::where('guest_id', $lockedGuest->id)->exists()) {
                    return $this->result('duplicate', 'Tamu ini sudah check-in.', $lockedGuest, $lockedGuest->checkin);
                }

                $checkin = Checkin::create([
                    'event_id' => $event->id,
                    'guest_id' => $lockedGuest->id,
                    'checked_in_by' => $officer->id,
                    'method' => CheckinMethod::Manual,
                    'checked_in_at' => now(),
                    'notes' => $notes,
                    'ip_address' => request()?->ip(),
                ]);

                $lockedGuest->forceFill(['checked_in_at' => now()])->save();

                $this->audit->record(
                    'checkin.manual',
                    "Check-in manual: {$lockedGuest->name}",
                    $event,
                    $officer,
                    $checkin,
                    ['guest_id' => $lockedGuest->id]
                );
                $this->audit->activity($event, 'checkin', "{$lockedGuest->name} check-in manual", $lockedGuest, $officer);

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
        return str_contains($e->getMessage(), 'Duplicate')
            || str_contains($e->getMessage(), 'UNIQUE')
            || (int) $e->getCode() === 23000;
    }

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
