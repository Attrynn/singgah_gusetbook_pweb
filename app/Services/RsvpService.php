<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Models\Guest;
use App\Models\Rsvp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RsvpService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function submit(Guest $guest, array $data): Rsvp
    {
        $guest->loadMissing('event');
        $event = $guest->event;

        if (! $event->rsvpOpen()) {
            throw ValidationException::withMessages([
                'rsvp' => 'RSVP untuk acara ini sudah ditutup.',
            ]);
        }

        if ($guest->status === GuestStatus::Blocked) {
            throw ValidationException::withMessages([
                'rsvp' => 'Undangan ini tidak dapat digunakan.',
            ]);
        }

        return $this->persist($guest, $data, null);
    }

    public function manualUpdate(Guest $guest, array $data, User $user): Rsvp
    {
        $guest->loadMissing('event');

        return $this->persist($guest, $data, $user);
    }

    protected function persist(Guest $guest, array $data, ?User $actor): Rsvp
    {
        $status = RsvpStatus::from($data['status']);
        $count = (int) ($data['attendance_count'] ?? 0);

        if ($status === RsvpStatus::Declined) {
            $count = 0;
        }

        if ($status === RsvpStatus::Confirmed) {
            if ($count < 1) {
                throw ValidationException::withMessages([
                    'attendance_count' => 'Jumlah kehadiran minimal 1 orang.',
                ]);
            }

            if ($count > $guest->max_attendees) {
                throw ValidationException::withMessages([
                    'attendance_count' => "Jumlah kehadiran melebihi batas undangan ({$guest->max_attendees} orang).",
                ]);
            }
        }

        return DB::transaction(function () use ($guest, $data, $status, $count, $actor) {
            $request = request();

            $rsvp = Rsvp::updateOrCreate(
                ['event_id' => $guest->event_id, 'guest_id' => $guest->id],
                [
                    'status' => $status,
                    'attendance_count' => $count,
                    'companion_names' => $data['companion_names'] ?? null,
                    'dietary_preference' => $data['dietary_preference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'responded_at' => now(),
                    'ip_address' => $request?->ip(),
                    'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
                ]
            );

            $guest->forceFill(['rsvp_status' => $status])->save();

            $this->audit->guestAction(
                $guest->event,
                $guest,
                'rsvp.submitted',
                "RSVP {$guest->name}: {$status->label()} ({$count} orang)",
                ['status' => $status->value, 'attendance_count' => $count]
            );

            if ($actor) {
                $this->audit->record(
                    'rsvp.manual_update',
                    "RSVP diperbarui manual oleh {$actor->name} untuk {$guest->name}",
                    $guest->event,
                    $actor,
                    $rsvp,
                    ['status' => $status->value]
                );
            }

            return $rsvp->fresh();
        });
    }
}
