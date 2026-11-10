<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\MembershipStatus;
use App\Enums\StationCategory;
use App\Enums\StationStatus;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped operational stations.
 *
 * A station never performs admission: check-in remains owned by M5. A station
 * assignment never grants check-in permission by itself.
 */
class StationService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function create(Event $event, array $data, User $actor): EventStation
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_MANAGE, 'Anda tidak berwenang mengelola stasiun.');

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Nama stasiun wajib diisi.']);
        }

        $supervisor = $this->resolveMembership($event, $data['supervisor_membership_id'] ?? null);

        return DB::transaction(function () use ($event, $data, $actor, $name, $supervisor): EventStation {
            $station = EventStation::create([
                'event_id' => $event->id,
                'name' => $name,
                'slug' => $this->uniqueSlug($event, $data['slug'] ?? $name),
                'category' => $this->resolveCategory($data['category'] ?? null),
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'status' => StationStatus::Draft,
                'supervisor_membership_id' => $supervisor?->id,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($supervisor !== null) {
                $this->upsertAssignment($event, $station, $supervisor, $actor, true);
            }

            $this->audit->record(
                'station.created',
                "Stasiun {$station->name} dibuat pada {$event->name}",
                $event,
                $actor,
                $station,
                ['category' => $station->category->value],
            );

            return $station->fresh();
        });
    }

    public function update(Event $event, EventStation $station, array $data, User $actor): EventStation
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_MANAGE, 'Anda tidak berwenang mengelola stasiun.');
        $this->assertStationBelongsToEvent($event, $station);

        $payload = [];

        foreach (['name', 'description', 'location', 'instructions'] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = $key === 'name' ? trim((string) $data[$key]) : $data[$key];
            }
        }

        if (isset($payload['name']) && $payload['name'] === '') {
            throw ValidationException::withMessages(['name' => 'Nama stasiun wajib diisi.']);
        }

        if (array_key_exists('category', $data)) {
            $payload['category'] = $this->resolveCategory($data['category']);
        }

        if (array_key_exists('slug', $data) && filled($data['slug'])) {
            $payload['slug'] = $this->uniqueSlug($event, $data['slug'], $station->id);
        }

        if (array_key_exists('supervisor_membership_id', $data)) {
            $payload['supervisor_membership_id'] = $this->resolveMembership($event, $data['supervisor_membership_id'])?->id;
        }

        $payload['updated_by'] = $actor->id;

        $station->update($payload);

        if (array_key_exists('supervisor_membership_id', $data)) {
            $this->syncSupervisorAssignment($event, $station->fresh(), $actor);
        }

        $this->audit->record(
            'station.updated',
            "Stasiun {$station->name} diperbarui pada {$event->name}",
            $event,
            $actor,
            $station,
            ['changed' => array_keys($payload)],
        );

        return $station->fresh();
    }

    public function delete(Event $event, EventStation $station, User $actor): void
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_MANAGE, 'Anda tidak berwenang menghapus stasiun.');
        $this->assertStationBelongsToEvent($event, $station);

        if ($station->activeAssignments()->exists()) {
            throw ValidationException::withMessages([
                'station' => 'Stasiun masih memiliki staf aktif. Lepaskan penugasan sebelum menghapus.',
            ]);
        }

        if (! in_array($station->status, [StationStatus::Draft, StationStatus::NotReady], true)) {
            throw ValidationException::withMessages([
                'station' => 'Hanya stasiun draft atau belum siap yang dapat dihapus. Tandai selesai untuk stasiun operasional.',
            ]);
        }

        $this->audit->record(
            'station.deleted',
            "Stasiun {$station->name} dihapus dari {$event->name}",
            $event,
            $actor,
            $station,
        );

        $station->delete();
    }

    public function transition(Event $event, EventStation $station, StationStatus $target, User $actor): EventStation
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_MANAGE, 'Anda tidak berwenang mengubah status stasiun.');
        $this->assertStationBelongsToEvent($event, $station);

        if (! $station->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Status stasiun tidak dapat berubah dari {$station->status->label()} ke {$target->label()}.",
            ]);
        }

        $from = $station->status;
        $station->update(['status' => $target, 'updated_by' => $actor->id]);

        $this->audit->record(
            'station.status_changed',
            "Status stasiun {$station->name}: {$from->label()} → {$target->label()}",
            $event,
            $actor,
            $station,
            ['from' => $from->value, 'to' => $target->value],
        );

        return $station->fresh();
    }

    public function assignStaff(Event $event, EventStation $station, EventStaffMembership $membership, User $actor, bool $isSupervisor = false): EventStationAssignment
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_ASSIGN, 'Anda tidak berwenang menugaskan staf ke stasiun.');
        $this->assertStationBelongsToEvent($event, $station);
        $this->assertActiveEventMembership($event, $membership);

        return DB::transaction(function () use ($event, $station, $membership, $actor, $isSupervisor): EventStationAssignment {
            $assignment = $this->upsertAssignment($event, $station, $membership, $actor, $isSupervisor);

            if ($isSupervisor) {
                $station->update(['supervisor_membership_id' => $membership->id, 'updated_by' => $actor->id]);
            }

            $this->audit->record(
                'station.assigned',
                "{$membership->user->name} ditugaskan ke stasiun {$station->name}",
                $event,
                $actor,
                $station,
                ['membership_id' => $membership->id, 'is_supervisor' => $isSupervisor],
            );

            return $assignment->fresh();
        });
    }

    public function unassignStaff(Event $event, EventStation $station, EventStaffMembership $membership, User $actor): void
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_ASSIGN, 'Anda tidak berwenang melepas penugasan stasiun.');
        $this->assertStationBelongsToEvent($event, $station);
        $this->assertActiveEventMembership($event, $membership);

        DB::transaction(function () use ($event, $station, $membership, $actor): void {
            $station->activeAssignments()
                ->where('staff_membership_id', $membership->id)
                ->update(['removed_at' => now(), 'is_supervisor' => false]);

            if ((int) $station->supervisor_membership_id === (int) $membership->id) {
                $station->update(['supervisor_membership_id' => null, 'updated_by' => $actor->id]);
            }

            $this->audit->record(
                'station.unassigned',
                "{$membership->user->name} dilepas dari stasiun {$station->name}",
                $event,
                $actor,
                $station,
                ['membership_id' => $membership->id],
            );
        });
    }

    public function setSupervisor(Event $event, EventStation $station, ?EventStaffMembership $membership, User $actor): EventStation
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::STATION_ASSIGN, 'Anda tidak berwenang menentukan supervisor stasiun.');
        $this->assertStationBelongsToEvent($event, $station);

        if ($membership !== null) {
            $this->assertActiveEventMembership($event, $membership);
        }

        return DB::transaction(function () use ($event, $station, $membership, $actor): EventStation {
            $station->update([
                'supervisor_membership_id' => $membership?->id,
                'updated_by' => $actor->id,
            ]);

            $this->syncSupervisorAssignment($event, $station->fresh(), $actor);

            $this->audit->record(
                'station.supervisor_changed',
                "Supervisor stasiun {$station->name} diperbarui",
                $event,
                $actor,
                $station,
                ['membership_id' => $membership?->id],
            );

            return $station->fresh();
        });
    }

    /**
     * @return array{level:string,label:string,assigned:int,has_supervisor:bool}
     */
    public function coverage(EventStation $station): array
    {
        $assigned = $station->activeAssignments()->count();
        $hasSupervisor = $station->supervisor_membership_id !== null;

        $level = match (true) {
            $assigned === 0 => 'no_staff',
            ! $hasSupervisor => 'no_supervisor',
            ! $station->status->isOperational() => 'not_ready',
            default => 'ready',
        };

        $label = match ($level) {
            'no_staff' => 'Belum ada staf',
            'no_supervisor' => 'Belum ada supervisor',
            'not_ready' => 'Belum siap',
            default => 'Siap beroperasi',
        };

        return [
            'level' => $level,
            'label' => $label,
            'assigned' => $assigned,
            'has_supervisor' => $hasSupervisor,
        ];
    }

    protected function upsertAssignment(Event $event, EventStation $station, EventStaffMembership $membership, User $actor, bool $isSupervisor): EventStationAssignment
    {
        return EventStationAssignment::updateOrCreate(
            [
                'station_id' => $station->id,
                'staff_membership_id' => $membership->id,
            ],
            [
                'event_id' => $event->id,
                'is_supervisor' => $isSupervisor,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
                'removed_at' => null,
            ],
        );
    }

    protected function syncSupervisorAssignment(Event $event, EventStation $station, User $actor): void
    {
        $station->activeAssignments()->update(['is_supervisor' => false]);

        if ($station->supervisor_membership_id === null) {
            return;
        }

        $membership = EventStaffMembership::query()
            ->whereKey($station->supervisor_membership_id)
            ->where('event_id', $event->id)
            ->first();

        if ($membership === null) {
            return;
        }

        $this->upsertAssignment($event, $station, $membership, $actor, true);
    }

    protected function resolveMembership(Event $event, mixed $membershipId): ?EventStaffMembership
    {
        if (blank($membershipId)) {
            return null;
        }

        $membership = EventStaffMembership::query()
            ->whereKey($membershipId)
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active)
            ->first();

        if ($membership === null) {
            throw ValidationException::withMessages([
                'supervisor_membership_id' => 'Supervisor harus anggota tim aktif dari event ini.',
            ]);
        }

        return $membership;
    }

    protected function resolveCategory(mixed $category): StationCategory
    {
        if ($category instanceof StationCategory) {
            return $category;
        }

        if (blank($category)) {
            return StationCategory::General;
        }

        $resolved = StationCategory::tryFrom((string) $category);

        if ($resolved === null) {
            throw ValidationException::withMessages(['category' => 'Kategori stasiun tidak dikenal.']);
        }

        return $resolved;
    }

    protected function uniqueSlug(Event $event, string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'stasiun';
        $slug = $base;
        $counter = 1;

        while (EventStation::query()
            ->where('event_id', $event->id)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        if (! $this->permissions->hasEventPermission($actor, $event, $permission)) {
            abort(403, $message);
        }
    }

    protected function assertStationBelongsToEvent(Event $event, EventStation $station): void
    {
        abort_unless((int) $station->event_id === (int) $event->id, 404);
    }

    protected function assertActiveEventMembership(Event $event, EventStaffMembership $membership): void
    {
        $isValid = (int) $membership->event_id === (int) $event->id
            && $membership->status === MembershipStatus::Active;

        if (! $isValid) {
            throw ValidationException::withMessages([
                'staff_membership_id' => 'Staf harus anggota tim aktif dari event ini.',
            ]);
        }
    }
}
