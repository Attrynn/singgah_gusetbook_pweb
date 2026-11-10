<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\RunOfShowStatus;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventRunOfShowItem;
use App\Models\EventSession;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Internal-only run of show management for a single event.
 *
 * Datetimes are authoritative in UTC: callers supply event-local wall-clock
 * values (`*_local` keys) which are converted using the event timezone before
 * they are stored. Conflict validation is explicit and can be bypassed only by
 * a caller that intentionally allows parallel activities.
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
        $station = $this->resolveStation($event, $data['station_id'] ?? null);
        $dependsOn = $this->resolveDependency($event, $data['depends_on_item_id'] ?? null);
        $membershipIds = $this->normalizeMembershipIds($event, $data['assignee_membership_ids'] ?? []);

        $payload = [
            'event_id' => $event->id,
            'event_session_id' => $this->resolveSession($event, $data['event_session_id'] ?? null)?->id,
            'phase_id' => $this->resolvePhase($event, $data['phase_id'] ?? null)?->id,
            'station_id' => $station?->id,
            'depends_on_item_id' => $dependsOn?->id,
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
        ];

        $this->assertChronology($payload);
        $this->assertNoConflicts(
            $event,
            $payload,
            null,
            array_merge($membershipIds, $payload['pic_staff_membership_id'] ? [$payload['pic_staff_membership_id']] : []),
            (bool) ($data['allow_parallel'] ?? false),
        );
        $this->assertDependencyOrdering($payload, $dependsOn);

        $item = DB::transaction(function () use ($event, $payload, $membershipIds, $actor): EventRunOfShowItem {
            $item = EventRunOfShowItem::create($payload);
            $this->syncAssignees($event, $item, $membershipIds);

            $this->audit->record(
                'run_of_show.created',
                "Item run of show dibuat: {$item->title}",
                $event,
                $actor,
                $item,
            );

            return $item;
        });

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

        if (array_key_exists('station_id', $data)) {
            $payload['station_id'] = $this->resolveStation($event, $data['station_id'])?->id;
        }

        if (array_key_exists('depends_on_item_id', $data)) {
            $dependsOn = $this->resolveDependency($event, $data['depends_on_item_id'], $item);

            if ($dependsOn !== null && $this->wouldCreateDependencyCycle($item, $dependsOn)) {
                throw ValidationException::withMessages([
                    'depends_on_item_id' => 'Dependensi ini membuat siklus yang tidak valid.',
                ]);
            }

            $payload['depends_on_item_id'] = $dependsOn?->id;
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

        $merged = array_merge($item->only([
            'planned_start', 'planned_end', 'actual_start', 'actual_end', 'station_id', 'pic_staff_membership_id',
        ]), $payload);

        $this->assertChronology($merged);

        $syncAssignees = array_key_exists('assignee_membership_ids', $data);

        $membershipIds = $syncAssignees
            ? $this->normalizeMembershipIds($event, $data['assignee_membership_ids'])
            : $item->assignees()->pluck('event_staff_memberships.id')->all();

        $conflictMembers = array_merge(
            $membershipIds,
            $merged['pic_staff_membership_id'] ? [$merged['pic_staff_membership_id']] : [],
        );

        $this->assertNoConflicts(
            $event,
            $merged,
            $item,
            $conflictMembers,
            (bool) ($data['allow_parallel'] ?? false),
        );

        if (array_key_exists('depends_on_item_id', $data) && $merged['depends_on_item_id']) {
            $this->assertDependencyOrdering($merged, EventRunOfShowItem::find($merged['depends_on_item_id']));
        }

        DB::transaction(function () use ($item, $payload, $event, $membershipIds, $syncAssignees, $actor): void {
            $item->update($payload);

            if ($syncAssignees) {
                $this->syncAssignees($event, $item->fresh(), $membershipIds);
            }

            $this->audit->record(
                'run_of_show.updated',
                "Item run of show diperbarui: {$item->title}",
                $event,
                $actor,
                $item,
            );
        });

        return $item->fresh();
    }

    public function transition(EventRunOfShowItem $item, RunOfShowStatus $target, User $actor, ?string $reason = null): EventRunOfShowItem
    {
        $event = $item->event;

        if (! $item->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Status aktivitas tidak dapat berubah dari {$item->status->label()} ke {$target->label()}.",
            ]);
        }

        $from = $item->status;
        $payload = [
            'status' => $target,
            'status_changed_at' => now(),
            'status_changed_by' => $actor->id,
            'updated_by' => $actor->id,
        ];

        if ($target === RunOfShowStatus::InProgress && $item->actual_start === null) {
            $payload['actual_start'] = now();
        }

        if ($target === RunOfShowStatus::Completed) {
            $payload['actual_end'] = now();
        }

        if ($target === RunOfShowStatus::InProgress && $from === RunOfShowStatus::Completed) {
            $payload['actual_end'] = null;
        }

        $item->update($payload);

        $this->audit->record(
            'run_of_show.status_changed',
            "Status aktivitas {$item->title}: {$from->label()} → {$target->label()}",
            $event,
            $actor,
            $item,
            ['from' => $from->value, 'to' => $target->value, 'reason' => $reason],
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

    /**
     * @param  array<int, int>  $membershipIds
     */
    public function syncAssignees(Event $event, EventRunOfShowItem $item, array $membershipIds): void
    {
        DB::table('event_run_of_show_assignees')
            ->where('run_of_show_item_id', $item->id)
            ->delete();

        foreach (array_unique($membershipIds) as $membershipId) {
            DB::table('event_run_of_show_assignees')->insert([
                'event_id' => $event->id,
                'run_of_show_item_id' => $item->id,
                'staff_membership_id' => $membershipId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int>  $membershipIds
     */
    protected function assertNoConflicts(Event $event, array $payload, ?EventRunOfShowItem $ignore, array $membershipIds, bool $allowParallel): void
    {
        $start = $payload['planned_start'] ?? null;
        $end = $payload['planned_end'] ?? null;

        if ($start === null || $end === null) {
            return;
        }

        $startAt = Carbon::parse($start);
        $endAt = Carbon::parse($end);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            throw ValidationException::withMessages([
                'planned_end_local' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        $overlapping = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', [RunOfShowStatus::Cancelled->value, RunOfShowStatus::Skipped->value])
            ->whereNotNull('planned_start')
            ->whereNotNull('planned_end')
            ->where('planned_start', '<', $endAt)
            ->where('planned_end', '>', $startAt)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->get();

        if ($overlapping->isEmpty() || $allowParallel) {
            return;
        }

        if ($membershipIds !== []) {
            $conflict = $overlapping->first(function (EventRunOfShowItem $item) use ($membershipIds): bool {
                $assigned = $item->assignees()->pluck('event_staff_memberships.id')->all();

                if ($item->pic_staff_membership_id !== null) {
                    $assigned[] = (int) $item->pic_staff_membership_id;
                }

                return array_intersect(array_map('intval', $assigned), array_map('intval', $membershipIds)) !== [];
            });

            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'assignee_membership_ids' => "Staf yang sama sudah ditugaskan pada aktivitas tumpang tindih: {$conflict->title}.",
                ]);
            }
        }

        $stationId = $payload['station_id'] ?? null;

        if ($stationId !== null) {
            $stationConflict = $overlapping->firstWhere('station_id', (int) $stationId);

            if ($stationConflict !== null) {
                throw ValidationException::withMessages([
                    'station_id' => "Stasiun ini sudah dipakai pada aktivitas tumpang tindih: {$stationConflict->title}.",
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertChronology(array $payload): void
    {
        $plannedStart = $payload['planned_start'] ?? null;
        $plannedEnd = $payload['planned_end'] ?? null;

        if ($plannedStart !== null && $plannedEnd !== null && Carbon::parse($plannedEnd)->lessThanOrEqualTo(Carbon::parse($plannedStart))) {
            throw ValidationException::withMessages([
                'planned_end_local' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        $actualStart = $payload['actual_start'] ?? null;
        $actualEnd = $payload['actual_end'] ?? null;

        if ($actualStart !== null && $actualEnd !== null && Carbon::parse($actualEnd)->lessThan(Carbon::parse($actualStart))) {
            throw ValidationException::withMessages([
                'actual_end_local' => 'Waktu selesai aktual tidak boleh mendahului waktu mulai aktual.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function assertDependencyOrdering(array $payload, ?EventRunOfShowItem $dependsOn): void
    {
        if ($dependsOn === null) {
            return;
        }

        $start = $payload['planned_start'] ?? null;

        if ($start === null || $dependsOn->planned_end === null) {
            return;
        }

        if (Carbon::parse($start)->lessThan($dependsOn->planned_end)) {
            throw ValidationException::withMessages([
                'depends_on_item_id' => 'Aktivitas ini tidak dapat dimulai sebelum prasyaratnya selesai.',
            ]);
        }
    }

    protected function wouldCreateDependencyCycle(EventRunOfShowItem $item, EventRunOfShowItem $dependsOn): bool
    {
        $stack = [$dependsOn->id];
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ((int) $current === (int) $item->id) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            $parent = EventRunOfShowItem::query()->whereKey($current)->value('depends_on_item_id');

            if ($parent !== null) {
                $stack[] = $parent;
            }
        }

        return false;
    }

    /**
     * @return array<int, int>
     */
    protected function normalizeMembershipIds(Event $event, mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));

        if ($ids === []) {
            return [];
        }

        $valid = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages([
                'assignee_membership_ids' => 'Semua staf yang ditugaskan harus anggota tim aktif event ini.',
            ]);
        }

        return $valid;
    }

    protected function resolveStation(Event $event, mixed $stationId): ?EventStation
    {
        if (blank($stationId)) {
            return null;
        }

        $station = EventStation::query()
            ->whereKey($stationId)
            ->where('event_id', $event->id)
            ->first();

        if ($station === null) {
            throw ValidationException::withMessages(['station_id' => 'Stasiun tidak ditemukan pada event ini.']);
        }

        return $station;
    }

    protected function resolveDependency(Event $event, mixed $itemId, ?EventRunOfShowItem $ignore = null): ?EventRunOfShowItem
    {
        if (blank($itemId)) {
            return null;
        }

        $item = EventRunOfShowItem::query()
            ->whereKey($itemId)
            ->where('event_id', $event->id)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages(['depends_on_item_id' => 'Prasyarat tidak ditemukan pada event ini.']);
        }

        if ($ignore !== null && (int) $item->id === (int) $ignore->id) {
            throw ValidationException::withMessages(['depends_on_item_id' => 'Aktivitas tidak dapat bergantung pada dirinya sendiri.']);
        }

        return $item;
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
