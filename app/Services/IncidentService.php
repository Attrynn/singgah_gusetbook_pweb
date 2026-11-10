<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventIncidentUpdate;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped operational incidents.
 *
 * Incidents are operational coordination records, not an emergency service.
 * High and critical incidents can only be closed after an authorized resolution.
 */
class IncidentService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function visibleIncidents(Event $event, User $user): Builder
    {
        $query = EventIncident::query()->where('event_id', $event->id);

        if ($this->permissions->hasEventPermission($user, $event, PermissionRegistry::INCIDENT_MANAGE)) {
            return $query;
        }

        $stationIds = $this->stationIdsForUser($event, $user);

        return $query->where(function (Builder $inner) use ($user, $stationIds): void {
            $inner->where('reported_by', $user->id)
                ->orWhere('assigned_to', $user->id)
                ->when($stationIds !== [], fn (Builder $q) => $q->orWhereIn('station_id', $stationIds));
        });
    }

    public function report(Event $event, array $data, User $actor): EventIncident
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_CREATE, 'Anda tidak berwenang melaporkan insiden.');
        $this->assertOperationalWindowOpen($event);

        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'Judul insiden wajib diisi.']);
        }

        $station = $this->resolveStation($event, $data['station_id'] ?? null);
        $assignee = $this->resolveManager($event, $data['assigned_to'] ?? null);

        return DB::transaction(function () use ($event, $data, $actor, $title, $station, $assignee): EventIncident {
            $incident = EventIncident::create([
                'event_id' => $event->id,
                'title' => $title,
                'description' => $data['description'] ?? null,
                'category' => $this->resolveCategory($data['category'] ?? null),
                'severity' => $this->resolveSeverity($data['severity'] ?? null),
                'status' => IncidentStatus::Open,
                'station_id' => $station?->id,
                'reported_by' => $actor->id,
                'assigned_to' => $assignee?->id,
                'reported_at' => now(),
            ]);

            $this->recordUpdate($incident, $actor, 'reported', null, IncidentStatus::Open, [
                'severity' => $incident->severity->value,
                'category' => $incident->category->value,
            ]);

            $this->audit->record(
                'incident.reported',
                "Insiden dilaporkan: {$incident->title}",
                $event,
                $actor,
                $incident,
                ['severity' => $incident->severity->value, 'category' => $incident->category->value],
            );

            return $incident->fresh();
        });
    }

    public function update(Event $event, EventIncident $incident, array $data, User $actor): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);
        $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_MANAGE, 'Anda tidak berwenang mengubah insiden.');

        if ($incident->status === IncidentStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'Insiden yang sudah ditutup harus dibuka kembali sebelum diubah.']);
        }

        $payload = [];

        if (array_key_exists('title', $data)) {
            $title = trim((string) $data['title']);

            if ($title === '') {
                throw ValidationException::withMessages(['title' => 'Judul insiden wajib diisi.']);
            }

            $payload['title'] = $title;
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $data['description'];
        }

        if (array_key_exists('category', $data)) {
            $payload['category'] = $this->resolveCategory($data['category']);
        }

        if (array_key_exists('severity', $data)) {
            $payload['severity'] = $this->resolveSeverity($data['severity']);
        }

        if (array_key_exists('station_id', $data)) {
            $payload['station_id'] = $this->resolveStation($event, $data['station_id'])?->id;
        }

        $before = $incident->only(['title', 'category', 'severity', 'station_id']);
        $incident->update($payload);

        $this->recordUpdate($incident, $actor, 'updated', $incident->status, $incident->status, [
            'before' => $before,
            'after' => $incident->only(array_keys($payload)),
        ]);

        $this->audit->record(
            'incident.updated',
            "Insiden diperbarui: {$incident->title}",
            $event,
            $actor,
            $incident,
        );

        return $incident->fresh();
    }

    public function assign(Event $event, EventIncident $incident, ?User $assignee, User $actor): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);
        $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_MANAGE, 'Anda tidak berwenang menugaskan insiden.');

        if ($incident->status === IncidentStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'Insiden yang sudah ditutup tidak dapat ditugaskan.']);
        }

        if ($assignee !== null) {
            $this->assertUserIsEventManager($event, $assignee);
        }

        $previous = $incident->assigned_to;
        $incident->update(['assigned_to' => $assignee?->id]);

        $this->recordUpdate($incident, $actor, 'assigned', $incident->status, $incident->status, [
            'from_user_id' => $previous,
            'to_user_id' => $assignee?->id,
        ]);

        $this->audit->record(
            'incident.assigned',
            "Penugasan insiden {$incident->title} diperbarui",
            $event,
            $actor,
            $incident,
            ['from_user_id' => $previous, 'to_user_id' => $assignee?->id],
        );

        return $incident->fresh();
    }

    public function transition(Event $event, EventIncident $incident, IncidentStatus $target, User $actor, ?string $notes = null): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);
        $this->assertActorMayHandle($event, $incident, $actor);

        if (! $incident->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Status insiden tidak dapat berubah dari {$incident->status->label()} ke {$target->label()}.",
            ]);
        }

        if (in_array($target, [IncidentStatus::Resolved, IncidentStatus::Closed], true)) {
            $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_RESOLVE, 'Anda tidak berwenang menyelesaikan insiden.');
        }

        return DB::transaction(function () use ($event, $incident, $target, $actor, $notes): EventIncident {
            $from = $incident->status;
            $payload = ['status' => $target];

            if ($target === IncidentStatus::Acknowledged) {
                $payload['acknowledged_at'] = now();
            }

            if ($target === IncidentStatus::InProgress) {
                $payload['resolved_at'] = null;
                $payload['resolved_by'] = null;
                $payload['closed_at'] = null;
                $payload['closed_by'] = null;
            }

            $incident->update($payload);

            $this->recordUpdate($incident, $actor, 'status_changed', $from, $target, [
                'notes' => $notes,
            ]);

            $this->audit->record(
                'incident.status_changed',
                "Status insiden {$incident->title}: {$from->label()} → {$target->label()}",
                $event,
                $actor,
                $incident,
                ['from' => $from->value, 'to' => $target->value, 'notes' => $notes],
            );

            return $incident->fresh();
        });
    }

    public function escalate(Event $event, EventIncident $incident, ?User $target, string $reason, User $actor, ?IncidentSeverity $severity = null): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);

        $isReporter = (int) $incident->reported_by === (int) $actor->id;

        if (! $isReporter) {
            $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_MANAGE, 'Anda tidak berwenang mengeskalasi insiden.');
        }

        if ($incident->status === IncidentStatus::Closed) {
            throw ValidationException::withMessages(['status' => 'Insiden yang sudah ditutup tidak dapat dieskalasi.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan eskalasi wajib diisi.']);
        }

        if ($target !== null) {
            $this->assertUserIsEventManager($event, $target);
        }

        if ($severity !== null && $severity->isSerious() === false && $incident->severity->isSerious()) {
            throw ValidationException::withMessages(['severity' => 'Eskalasi tidak dapat menurunkan tingkat keparahan.']);
        }

        return DB::transaction(function () use ($event, $incident, $target, $reason, $actor, $severity): EventIncident {
            $payload = [
                'escalated_at' => now(),
                'escalated_to' => $target?->id,
                'escalation_reason' => $reason,
            ];

            if ($severity !== null) {
                $payload['severity'] = $severity;
            }

            $incident->update($payload);

            $this->recordUpdate($incident, $actor, 'escalated', $incident->status, $incident->status, [
                'escalated_to' => $target?->id,
                'reason' => $reason,
                'severity' => $severity?->value,
            ]);

            $this->audit->record(
                'incident.escalated',
                "Insiden dieskalasi: {$incident->title}",
                $event,
                $actor,
                $incident,
                ['escalated_to' => $target?->id, 'reason' => $reason],
            );

            return $incident->fresh();
        });
    }

    public function resolve(Event $event, EventIncident $incident, string $notes, User $actor): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);
        $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_RESOLVE, 'Anda tidak berwenang menyelesaikan insiden.');

        if (! $incident->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Hanya insiden terbuka yang dapat diselesaikan.']);
        }

        $notes = trim($notes);

        if ($notes === '') {
            throw ValidationException::withMessages(['resolution_notes' => 'Catatan penyelesaian wajib diisi.']);
        }

        return DB::transaction(function () use ($event, $incident, $notes, $actor): EventIncident {
            $from = $incident->status;

            $incident->update([
                'status' => IncidentStatus::Resolved,
                'resolution_notes' => $notes,
                'resolved_at' => now(),
                'resolved_by' => $actor->id,
            ]);

            $this->recordUpdate($incident, $actor, 'resolved', $from, IncidentStatus::Resolved, [
                'resolution_notes' => $notes,
            ]);

            $this->audit->record(
                'incident.resolved',
                "Insiden diselesaikan: {$incident->title}",
                $event,
                $actor,
                $incident,
                ['resolution_notes' => $notes],
            );

            return $incident->fresh();
        });
    }

    public function close(Event $event, EventIncident $incident, User $actor, ?string $notes = null): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);
        $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_MANAGE, 'Anda tidak berwenang menutup insiden.');

        if ($incident->status !== IncidentStatus::Resolved) {
            throw ValidationException::withMessages([
                'status' => 'Insiden harus diselesaikan terlebih dahulu sebelum ditutup.',
            ]);
        }

        if ($incident->severity->isSerious() && blank($incident->resolution_notes)) {
            throw ValidationException::withMessages([
                'status' => 'Insiden berkeparahan tinggi wajib memiliki catatan penyelesaian.',
            ]);
        }

        return DB::transaction(function () use ($event, $incident, $actor, $notes): EventIncident {
            $incident->update([
                'status' => IncidentStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ]);

            $this->recordUpdate($incident, $actor, 'closed', IncidentStatus::Resolved, IncidentStatus::Closed, [
                'notes' => $notes,
            ]);

            $this->audit->record(
                'incident.closed',
                "Insiden ditutup: {$incident->title}",
                $event,
                $actor,
                $incident,
            );

            return $incident->fresh();
        });
    }

    public function reopen(Event $event, EventIncident $incident, string $reason, User $actor): EventIncident
    {
        $this->assertIncidentBelongsToEvent($event, $incident);
        $this->assertActorHas($event, $actor, PermissionRegistry::INCIDENT_MANAGE, 'Anda tidak berwenang membuka kembali insiden.');

        if (! in_array($incident->status, [IncidentStatus::Resolved, IncidentStatus::Closed], true)) {
            throw ValidationException::withMessages(['status' => 'Hanya insiden teratasi atau ditutup yang dapat dibuka kembali.']);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Alasan membuka kembali wajib diisi.']);
        }

        return DB::transaction(function () use ($event, $incident, $reason, $actor): EventIncident {
            $from = $incident->status;

            $incident->update([
                'status' => IncidentStatus::InProgress,
                'resolved_at' => null,
                'resolved_by' => null,
                'closed_at' => null,
                'closed_by' => null,
            ]);

            $this->recordUpdate($incident, $actor, 'reopened', $from, IncidentStatus::InProgress, [
                'reason' => $reason,
            ]);

            $this->audit->record(
                'incident.reopened',
                "Insiden dibuka kembali: {$incident->title}",
                $event,
                $actor,
                $incident,
                ['reason' => $reason],
            );

            return $incident->fresh();
        });
    }

    protected function recordUpdate(EventIncident $incident, User $actor, string $type, ?IncidentStatus $from, ?IncidentStatus $to, array $metadata = []): EventIncidentUpdate
    {
        return EventIncidentUpdate::create([
            'event_incident_id' => $incident->id,
            'actor_id' => $actor->id,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'metadata' => $metadata ?: null,
        ]);
    }

    protected function assertActorMayHandle(Event $event, EventIncident $incident, User $actor): void
    {
        if ($this->permissions->hasEventPermission($actor, $event, PermissionRegistry::INCIDENT_MANAGE)) {
            return;
        }

        if ((int) $incident->assigned_to === (int) $actor->id) {
            return;
        }

        abort(403, 'Anda tidak berwenang menangani insiden ini.');
    }

    protected function assertUserIsEventManager(Event $event, User $user): void
    {
        $hasMembership = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->exists();

        if (! $hasMembership) {
            throw ValidationException::withMessages(['assigned_to' => 'Penanggung jawab harus anggota tim aktif event ini.']);
        }

        if (! $this->permissions->hasEventPermission($user, $event, PermissionRegistry::INCIDENT_MANAGE)) {
            throw ValidationException::withMessages(['assigned_to' => 'Penanggung jawab harus memiliki izin menangani insiden.']);
        }
    }

    protected function resolveManager(Event $event, mixed $userId): ?User
    {
        if (blank($userId)) {
            return null;
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            throw ValidationException::withMessages(['assigned_to' => 'Pengguna tidak ditemukan.']);
        }

        $this->assertUserIsEventManager($event, $user);

        return $user;
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

    protected function resolveCategory(mixed $category): IncidentCategory
    {
        if ($category instanceof IncidentCategory) {
            return $category;
        }

        if (blank($category)) {
            return IncidentCategory::Other;
        }

        $resolved = IncidentCategory::tryFrom((string) $category);

        if ($resolved === null) {
            throw ValidationException::withMessages(['category' => 'Kategori insiden tidak dikenal.']);
        }

        return $resolved;
    }

    protected function resolveSeverity(mixed $severity): IncidentSeverity
    {
        if ($severity instanceof IncidentSeverity) {
            return $severity;
        }

        if (blank($severity)) {
            return IncidentSeverity::Medium;
        }

        $resolved = IncidentSeverity::tryFrom((string) $severity);

        if ($resolved === null) {
            throw ValidationException::withMessages(['severity' => 'Tingkat keparahan tidak dikenal.']);
        }

        return $resolved;
    }

    /**
     * @return array<int, int>
     */
    protected function stationIdsForUser(Event $event, User $user): array
    {
        $membershipIds = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active->value)
            ->pluck('id')
            ->all();

        if ($membershipIds === []) {
            return [];
        }

        return EventStationAssignment::query()
            ->whereIn('staff_membership_id', $membershipIds)
            ->whereNull('removed_at')
            ->pluck('station_id')
            ->all();
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        if (! $this->permissions->hasEventPermission($actor, $event, $permission)) {
            abort(403, $message);
        }
    }

    protected function assertIncidentBelongsToEvent(Event $event, EventIncident $incident): void
    {
        abort_unless((int) $incident->event_id === (int) $event->id, 404);
    }

    protected function assertOperationalWindowOpen(Event $event): void
    {
        $operation = $event->operation()->first();

        if ($operation?->isCompleted()) {
            throw ValidationException::withMessages([
                'event' => 'Operasional event sudah ditutup. Buka kembali operasional untuk melaporkan insiden baru.',
            ]);
        }
    }
}
