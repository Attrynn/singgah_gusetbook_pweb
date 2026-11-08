<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\RunOfShowStatus;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventRunOfShowItem;
use App\Models\EventSession;
use App\Models\EventStaffMembership;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Internal-only run of show management for a single event.
 *
 * Datetimes are authoritative in UTC: callers supply event-local wall-clock
 * values (`*_local` keys) which are converted using the event timezone before
 * they are stored.
 */
class RunOfShowService
{
    public function __construct(protected AuditService $audit) {}

    public function create(Event $event, array $data, User $actor): EventRunOfShowItem
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Judul run of show wajib diisi.']);
        }

        $timezone = $event->timezoneName();

        $item = EventRunOfShowItem::create([
            'event_id' => $event->id,
            'event_session_id' => $this->resolveSession($event, $data['event_session_id'] ?? null)?->id,
            'phase_id' => $this->resolvePhase($event, $data['phase_id'] ?? null)?->id,
            'title' => $title,
            'internal_description' => $data['internal_description'] ?? null,
            'planned_start' => $this->toUtc($data['planned_start_local'] ?? null, $timezone),
            'planned_end' => $this->toUtc($data['planned_end_local'] ?? null, $timezone),
            'actual_start' => $this->toUtc($data['actual_start_local'] ?? null, $timezone),
            'actual_end' => $this->toUtc($data['actual_end_local'] ?? null, $timezone),
            'status' => $this->resolveStatus($data['status'] ?? null),
            'sequence' => $this->resolveSequence($event, $data['sequence'] ?? null),
            'pic_staff_membership_id' => $this->resolvePic($event, $data['pic_staff_membership_id'] ?? null)?->id,
            'internal_notes' => $data['internal_notes'] ?? null,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record(
            'run_of_show.created',
            "Item run of show dibuat: {$item->title}",
            $event,
            $actor,
            $item,
        );

        return $item->fresh();
    }

    public function update(EventRunOfShowItem $item, array $data, User $actor): EventRunOfShowItem
    {
        $event = $item->event;
        $timezone = $event->timezoneName();
        $payload = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);

            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'Judul run of show wajib diisi.']);
            }

            $payload['title'] = $title;
        }

        foreach (['internal_description', 'internal_notes'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        if (array_key_exists('event_session_id', $data)) {
            $payload['event_session_id'] = $this->resolveSession($event, $data['event_session_id'])?->id;
        }

        if (array_key_exists('phase_id', $data)) {
            $payload['phase_id'] = $this->resolvePhase($event, $data['phase_id'])?->id;
        }

        if (array_key_exists('pic_staff_membership_id', $data)) {
            $payload['pic_staff_membership_id'] = $this->resolvePic($event, $data['pic_staff_membership_id'])?->id;
        }

        foreach (['planned_start_local', 'planned_end_local', 'actual_start_local', 'actual_end_local'] as $key) {
            if (array_key_exists($key, $data)) {
                $column = str_replace('_local', '', $key);
                $payload[$column] = $this->toUtc($data[$key], $timezone);
            }
        }

        if (array_key_exists('status', $data)) {
            $payload['status'] = $this->resolveStatus($data['status']);
        }

        if (array_key_exists('sequence', $data)) {
            $payload['sequence'] = (int) $data['sequence'];
        }

        $payload['updated_by'] = $actor->id;

        $item->update($payload);

        $this->audit->record(
            'run_of_show.updated',
            "Item run of show diperbarui: {$item->title}",
            $event,
            $actor,
            $item,
        );

        return $item->fresh();
    }

    public function delete(EventRunOfShowItem $item, User $actor): void
    {
        $event = $item->event;

        $this->audit->record(
            'run_of_show.deleted',
            "Item run of show dihapus: {$item->title}",
            $event,
            $actor,
            $item,
        );

        $item->delete();
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(Event $event, array $orderedIds, User $actor): void
    {
        DB::transaction(function () use ($event, $orderedIds): void {
            foreach (array_values($orderedIds) as $sequence => $id) {
                $event->runOfShowItems()
                    ->whereKey($id)
                    ->update(['sequence' => $sequence]);
            }
        });
    }

    protected function resolveSession(Event $event, mixed $sessionId): ?EventSession
    {
        if (blank($sessionId)) {
            return null;
        }

        $session = EventSession::query()
            ->whereKey($sessionId)
            ->where('event_id', $event->id)
            ->first();

        if (! $session) {
            abort(422, 'Sesi tidak ditemukan pada event ini.');
        }

        return $session;
    }

    protected function resolvePhase(Event $event, mixed $phaseId): ?EventPhase
    {
        if (blank($phaseId)) {
            return null;
        }

        $phase = EventPhase::query()
            ->whereKey($phaseId)
            ->where('event_id', $event->id)
            ->first();

        if (! $phase) {
            abort(422, 'Fase tidak ditemukan pada event ini.');
        }

        return $phase;
    }

    protected function resolvePic(Event $event, mixed $membershipId): ?EventStaffMembership
    {
        if (blank($membershipId)) {
            return null;
        }

        $membership = EventStaffMembership::query()
            ->whereKey($membershipId)
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active)
            ->first();

        if (! $membership) {
            abort(422, 'PIC harus anggota tim aktif dari event ini.');
        }

        return $membership;
    }

    protected function resolveStatus(mixed $status): RunOfShowStatus
    {
        if ($status instanceof RunOfShowStatus) {
            return $status;
        }

        if (blank($status)) {
            return RunOfShowStatus::Pending;
        }

        $resolved = RunOfShowStatus::tryFrom((string) $status);

        if (! $resolved) {
            throw ValidationException::withMessages(['status' => 'Status run of show tidak dikenal.']);
        }

        return $resolved;
    }

    protected function resolveSequence(Event $event, mixed $sequence): int
    {
        if ($sequence !== null && $sequence !== '') {
            return (int) $sequence;
        }

        return ((int) $event->runOfShowItems()->max('sequence')) + 1;
    }

    protected function toUtc(mixed $value, string $timezone): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, $timezone)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['datetime' => 'Format waktu tidak valid.']);
        }
    }
}
