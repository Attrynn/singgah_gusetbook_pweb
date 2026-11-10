<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\MembershipStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventRunOfShowItem;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\EventTask;
use App\Models\EventTaskChecklistItem;
use App\Models\EventTaskComment;
use App\Models\EventTaskDependency;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped operational tasks, checklists, dependencies, and comments.
 *
 * Row-level visibility: managers see every event task; other staff only see
 * tasks assigned to them, attached to their stations, or created by them.
 */
class TaskService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function visibleTasks(Event $event, User $user): Builder
    {
        $query = EventTask::query()->where('event_id', $event->id);

        if ($this->permissions->hasEventPermission($user, $event, PermissionRegistry::TASK_MANAGE)) {
            return $query;
        }

        $membershipIds = $this->activeMembershipIds($event, $user);
        $stationIds = $this->stationIdsFor($membershipIds);

        return $query->where(function (Builder $inner) use ($membershipIds, $stationIds, $user): void {
            $inner->whereIn('assigned_membership_id', $membershipIds)
                ->orWhereIn('station_id', $stationIds)
                ->orWhere('created_by', $user->id);
        });
    }

    public function create(Event $event, array $data, User $actor): EventTask
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::TASK_CREATE, 'Anda tidak berwenang membuat tugas.');
        $this->assertOperationalWindowOpen($event);

        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Judul tugas wajib diisi.']);
        }

        $assignee = $this->resolveAssignee($event, $data['assigned_membership_id'] ?? null, $actor);
        $station = $this->resolveStation($event, $data['station_id'] ?? null);
        $runOfShowItem = $this->resolveRunOfShowItem($event, $data['run_of_show_item_id'] ?? null);

        $task = EventTask::create([
            'event_id' => $event->id,
            'title' => $title,
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'priority' => $this->resolvePriority($data['priority'] ?? null),
            'status' => $assignee !== null ? TaskStatus::Assigned : TaskStatus::Open,
            'assigned_membership_id' => $assignee?->id,
            'station_id' => $station?->id,
            'run_of_show_item_id' => $runOfShowItem?->id,
            'due_at' => $this->toUtc($data['due_at'] ?? null, $event),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record(
            'task.created',
            "Tugas dibuat: {$task->title}",
            $event,
            $actor,
            $task,
            ['priority' => $task->priority->value, 'assigned_membership_id' => $task->assigned_membership_id],
        );

        return $task->fresh();
    }

    public function update(Event $event, EventTask $task, array $data, User $actor): EventTask
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertActorHas($event, $actor, PermissionRegistry::TASK_MANAGE, 'Anda tidak berwenang mengubah detail tugas.');

        $payload = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);

            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'Judul tugas wajib diisi.']);
            }

            $payload['title'] = $title;
        }

        foreach (['description', 'category'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $data[$key];
            }
        }

        if (array_key_exists('priority', $data)) {
            $payload['priority'] = $this->resolvePriority($data['priority']);
        }

        if (array_key_exists('station_id', $data)) {
            $payload['station_id'] = $this->resolveStation($event, $data['station_id'])?->id;
        }

        if (array_key_exists('run_of_show_item_id', $data)) {
            $payload['run_of_show_item_id'] = $this->resolveRunOfShowItem($event, $data['run_of_show_item_id'])?->id;
        }

        if (array_key_exists('due_at', $data)) {
            $payload['due_at'] = $this->toUtc($data['due_at'], $event);
        }

        $payload['updated_by'] = $actor->id;

        $before = $task->only(['title', 'priority', 'due_at', 'station_id', 'run_of_show_item_id']);
        $task->update($payload);

        $this->audit->record(
            'task.updated',
            "Tugas diperbarui: {$task->title}",
            $event,
            $actor,
            $task,
            ['before' => $before, 'after' => $task->only(array_keys($payload))],
        );

        return $task->fresh();
    }

    public function assign(Event $event, EventTask $task, ?EventStaffMembership $membership, User $actor): EventTask
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertActorHas($event, $actor, PermissionRegistry::TASK_ASSIGN, 'Anda tidak berwenang menugaskan tugas.');

        if ($task->status->isClosed()) {
            throw ValidationException::withMessages(['status' => 'Tugas yang sudah selesai atau dibatalkan tidak dapat ditugaskan ulang.']);
        }

        if ($membership !== null) {
            $this->assertActiveEventMembership($event, $membership);
        }

        $previous = $task->assigned_membership_id;
        $status = $membership !== null
            ? ($task->status === TaskStatus::Open ? TaskStatus::Assigned : $task->status)
            : TaskStatus::Open;

        $task->update([
            'assigned_membership_id' => $membership?->id,
            'status' => $status,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record(
            'task.assigned',
            "Penugasan tugas {$task->title} diperbarui",
            $event,
            $actor,
            $task,
            ['from_membership_id' => $previous, 'to_membership_id' => $membership?->id],
        );

        return $task->fresh();
    }

    public function transition(Event $event, EventTask $task, TaskStatus $target, User $actor, ?string $reason = null, bool $override = false): EventTask
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertActorMayProgress($event, $task, $actor);

        if (! $task->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Status tugas tidak dapat berubah dari {$task->status->label()} ke {$target->label()}.",
            ]);
        }

        if ($target === TaskStatus::Blocked && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan wajib diisi saat menandai tugas terhambat.']);
        }

        if ($target === TaskStatus::Completed) {
            $this->assertChecklistComplete($task);
            $this->assertDependenciesComplete($event, $task, $actor, $override, $reason);
        }

        $from = $task->status;
        $payload = [
            'status' => $target,
            'updated_by' => $actor->id,
            'blocked_reason' => $target === TaskStatus::Blocked ? $reason : null,
        ];

        if ($target === TaskStatus::Completed) {
            $payload['completed_at'] = now();
        } elseif ($from === TaskStatus::Completed) {
            $payload['completed_at'] = null;
        }

        if ($target === TaskStatus::Cancelled) {
            $payload['cancelled_at'] = now();
        } elseif ($from === TaskStatus::Cancelled) {
            $payload['cancelled_at'] = null;
        }

        $task->update($payload);

        $this->audit->record(
            'task.status_changed',
            "Status tugas {$task->title}: {$from->label()} → {$target->label()}",
            $event,
            $actor,
            $task,
            ['from' => $from->value, 'to' => $target->value, 'reason' => $reason, 'override' => $override],
        );

        return $task->fresh();
    }

    public function addChecklistItem(Event $event, EventTask $task, string $label, User $actor): EventTaskChecklistItem
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertActorMayProgress($event, $task, $actor);

        $label = trim($label);

        if ($label === '') {
            throw ValidationException::withMessages(['label' => 'Label checklist wajib diisi.']);
        }

        if ($task->status === TaskStatus::Completed) {
            throw ValidationException::withMessages(['status' => 'Checklist tugas selesai tidak dapat diubah tanpa membuka kembali tugas.']);
        }

        $sequence = ((int) $task->checklistItems()->max('sequence')) + 1;

        $item = EventTaskChecklistItem::create([
            'event_task_id' => $task->id,
            'label' => $label,
            'sequence' => $sequence,
        ]);

        $this->audit->record(
            'task.checklist_added',
            "Checklist ditambahkan pada tugas {$task->title}",
            $event,
            $actor,
            $task,
            ['label' => $label],
        );

        return $item->fresh();
    }

    public function updateChecklistItem(Event $event, EventTask $task, EventTaskChecklistItem $item, array $data, User $actor): EventTaskChecklistItem
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertChecklistItemBelongsToTask($task, $item);
        $this->assertActorMayProgress($event, $task, $actor);

        if ($task->status === TaskStatus::Completed) {
            $this->assertActorHas($event, $actor, PermissionRegistry::TASK_MANAGE, 'Tugas selesai hanya dapat diubah oleh pengelola tugas.');
        }

        $payload = [];

        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);

            if ($label === '') {
                throw ValidationException::withMessages(['label' => 'Label checklist wajib diisi.']);
            }

            $payload['label'] = $label;
        }

        if (array_key_exists('sequence', $data)) {
            $payload['sequence'] = max(0, (int) $data['sequence']);
        }

        if (array_key_exists('completed', $data)) {
            $completed = (bool) $data['completed'];
            $payload['completed'] = $completed;
            $payload['completed_at'] = $completed ? now() : null;
            $payload['completed_by'] = $completed ? $actor->id : null;
        }

        $item->update($payload);

        $this->audit->record(
            'task.checklist_updated',
            "Checklist diperbarui pada tugas {$task->title}",
            $event,
            $actor,
            $task,
            ['item_id' => $item->id, 'changed' => array_keys($payload)],
        );

        return $item->fresh();
    }

    public function deleteChecklistItem(Event $event, EventTask $task, EventTaskChecklistItem $item, User $actor): void
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertChecklistItemBelongsToTask($task, $item);
        $this->assertActorHas($event, $actor, PermissionRegistry::TASK_MANAGE, 'Anda tidak berwenang menghapus checklist.');

        $this->audit->record(
            'task.checklist_deleted',
            "Checklist dihapus dari tugas {$task->title}",
            $event,
            $actor,
            $task,
            ['label' => $item->label],
        );

        $item->delete();
    }

    public function addComment(Event $event, EventTask $task, string $body, User $actor): EventTaskComment
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertTaskIsVisibleTo($event, $task, $actor);

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Komentar tidak boleh kosong.']);
        }

        if (mb_strlen($body) > 2000) {
            throw ValidationException::withMessages(['body' => 'Komentar maksimal 2000 karakter.']);
        }

        $comment = EventTaskComment::create([
            'event_task_id' => $task->id,
            'user_id' => $actor->id,
            'body' => $body,
        ]);

        $this->audit->record(
            'task.comment_added',
            "Komentar ditambahkan pada tugas {$task->title}",
            $event,
            $actor,
            $task,
        );

        return $comment->fresh();
    }

    public function addDependency(Event $event, EventTask $task, EventTask $dependsOn, User $actor): EventTaskDependency
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertTaskBelongsToEvent($event, $dependsOn);
        $this->assertActorHas($event, $actor, PermissionRegistry::TASK_MANAGE, 'Anda tidak berwenang mengatur dependensi tugas.');

        if ((int) $task->id === (int) $dependsOn->id) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Tugas tidak dapat bergantung pada dirinya sendiri.']);
        }

        if ($this->wouldCreateCycle($task, $dependsOn)) {
            throw ValidationException::withMessages(['depends_on_task_id' => 'Dependensi ini membuat siklus yang tidak valid.']);
        }

        $dependency = EventTaskDependency::firstOrCreate([
            'event_task_id' => $task->id,
            'depends_on_task_id' => $dependsOn->id,
        ]);

        $this->audit->record(
            'task.dependency_added',
            "Dependensi ditambahkan pada tugas {$task->title}",
            $event,
            $actor,
            $task,
            ['depends_on_task_id' => $dependsOn->id],
        );

        return $dependency;
    }

    public function removeDependency(Event $event, EventTask $task, EventTaskDependency $dependency, User $actor): void
    {
        $this->assertTaskBelongsToEvent($event, $task);
        $this->assertActorHas($event, $actor, PermissionRegistry::TASK_MANAGE, 'Anda tidak berwenang mengatur dependensi tugas.');

        if ((int) $dependency->event_task_id !== (int) $task->id) {
            abort(404);
        }

        $this->audit->record(
            'task.dependency_removed',
            "Dependensi dihapus dari tugas {$task->title}",
            $event,
            $actor,
            $task,
            ['depends_on_task_id' => $dependency->depends_on_task_id],
        );

        $dependency->delete();
    }

    protected function assertChecklistComplete(EventTask $task): void
    {
        $incomplete = $task->checklistItems()->where('completed', false)->count();

        if ($incomplete > 0) {
            throw ValidationException::withMessages([
                'status' => "Masih ada {$incomplete} item checklist yang belum selesai.",
            ]);
        }
    }

    protected function assertDependenciesComplete(Event $event, EventTask $task, User $actor, bool $override, ?string $reason): void
    {
        $blocking = $task->dependencies()
            ->where('status', '!=', TaskStatus::Completed->value)
            ->get();

        if ($blocking->isEmpty()) {
            return;
        }

        if (! $override || ! $this->permissions->hasEventPermission($actor, $event, PermissionRegistry::TASK_MANAGE)) {
            throw ValidationException::withMessages([
                'status' => 'Tugas prasyarat belum selesai: '.$blocking->pluck('title')->join(', '),
            ]);
        }

        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Alasan override wajib diisi.']);
        }
    }

    protected function wouldCreateCycle(EventTask $task, EventTask $dependsOn): bool
    {
        $stack = [$dependsOn->id];
        $seen = [];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ((int) $current === (int) $task->id) {
                return true;
            }

            if (isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;

            $next = EventTaskDependency::query()
                ->where('event_task_id', $current)
                ->pluck('depends_on_task_id')
                ->all();

            foreach ($next as $id) {
                $stack[] = $id;
            }
        }

        return false;
    }

    protected function resolveAssignee(Event $event, mixed $membershipId, User $actor): ?EventStaffMembership
    {
        if (blank($membershipId)) {
            return null;
        }

        $membership = EventStaffMembership::query()
            ->whereKey($membershipId)
            ->where('event_id', $event->id)
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages(['assigned_membership_id' => 'Penanggung jawab bukan anggota tim event ini.']);
        }

        $this->assertActiveEventMembership($event, $membership);

        $isSelf = (int) $membership->user_id === (int) $actor->id;

        if (! $isSelf && ! $this->permissions->hasEventPermission($actor, $event, PermissionRegistry::TASK_ASSIGN)) {
            throw ValidationException::withMessages(['assigned_membership_id' => 'Anda tidak berwenang menugaskan tugas kepada staf lain.']);
        }

        return $membership;
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

    protected function resolveRunOfShowItem(Event $event, mixed $itemId): ?EventRunOfShowItem
    {
        if (blank($itemId)) {
            return null;
        }

        $item = EventRunOfShowItem::query()
            ->whereKey($itemId)
            ->where('event_id', $event->id)
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages(['run_of_show_item_id' => 'Item run of show tidak ditemukan pada event ini.']);
        }

        return $item;
    }

    protected function resolvePriority(mixed $priority): TaskPriority
    {
        if ($priority instanceof TaskPriority) {
            return $priority;
        }

        if (blank($priority)) {
            return TaskPriority::Medium;
        }

        $resolved = TaskPriority::tryFrom((string) $priority);

        if ($resolved === null) {
            throw ValidationException::withMessages(['priority' => 'Prioritas tugas tidak dikenal.']);
        }

        return $resolved;
    }

    protected function toUtc(mixed $value, Event $event): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value, $event->timezoneName())->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['due_at' => 'Format tenggat tidak valid.']);
        }
    }

    /**
     * @return array<int, int>
     */
    protected function activeMembershipIds(Event $event, User $user): array
    {
        return EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $membershipIds
     * @return array<int, int>
     */
    protected function stationIdsFor(array $membershipIds): array
    {
        if ($membershipIds === []) {
            return [];
        }

        return EventStationAssignment::query()
            ->whereIn('staff_membership_id', $membershipIds)
            ->whereNull('removed_at')
            ->pluck('station_id')
            ->all();
    }

    protected function assertActorMayProgress(Event $event, EventTask $task, User $actor): void
    {
        if ($this->permissions->hasEventPermission($actor, $event, PermissionRegistry::TASK_MANAGE)) {
            return;
        }

        $isAssignee = $task->assigned_membership_id !== null
            && in_array((int) $task->assigned_membership_id, $this->activeMembershipIds($event, $actor), true);

        if (! $isAssignee) {
            abort(403, 'Anda tidak berwenang memperbarui tugas ini.');
        }
    }

    protected function assertTaskIsVisibleTo(Event $event, EventTask $task, User $actor): void
    {
        $visible = $this->visibleTasks($event, $actor)->whereKey($task->id)->exists();

        abort_unless($visible, 404);
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        if (! $this->permissions->hasEventPermission($actor, $event, $permission)) {
            abort(403, $message);
        }
    }

    protected function assertTaskBelongsToEvent(Event $event, EventTask $task): void
    {
        abort_unless((int) $task->event_id === (int) $event->id, 404);
    }

    protected function assertChecklistItemBelongsToTask(EventTask $task, EventTaskChecklistItem $item): void
    {
        abort_unless((int) $item->event_task_id === (int) $task->id, 404);
    }

    protected function assertActiveEventMembership(Event $event, EventStaffMembership $membership): void
    {
        $isValid = (int) $membership->event_id === (int) $event->id
            && $membership->status === MembershipStatus::Active;

        if (! $isValid) {
            throw ValidationException::withMessages([
                'assigned_membership_id' => 'Penanggung jawab harus anggota tim aktif dari event ini.',
            ]);
        }
    }

    protected function assertOperationalWindowOpen(Event $event): void
    {
        $operation = $event->operation()->first();

        if ($operation?->isCompleted()) {
            throw ValidationException::withMessages([
                'event' => 'Operasional event sudah ditutup. Buka kembali operasional untuk membuat tugas baru.',
            ]);
        }
    }
}
