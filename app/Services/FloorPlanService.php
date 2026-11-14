<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\FloorPlanStatus;
use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSpace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Event-scoped floor plan documents. Each plan owns an ordered chain of
 * versions; only drafts are editable.
 */
class FloorPlanService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
        protected FloorPlanVersionService $versions,
    ) {}

    /**
     * @return Collection<int, FloorPlan>
     */
    public function listForEvent(Event $event): Collection
    {
        return FloorPlan::query()
            ->where('event_id', $event->id)
            ->with(['venue', 'venueSpace', 'currentDraft', 'publishedVersion'])
            ->orderBy('name')
            ->get();
    }

    public function create(Event $event, array $data, User $actor): FloorPlan
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_MANAGE, 'Anda tidak berwenang mengelola floor plan.');

        $name = $this->requiredName($data['name'] ?? null);
        $this->assertUniqueName($event, $name);

        [$venue, $space] = $this->resolveVenueLinks($event, $data);

        return DB::transaction(function () use ($event, $data, $actor, $name, $venue, $space): FloorPlan {
            $plan = FloorPlan::create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'venue_id' => $venue?->id,
                'venue_space_id' => $space?->id,
                'name' => $name,
                'description' => $this->plainText($data['description'] ?? null),
                'status' => FloorPlanStatus::Active,
                'created_by' => $actor->id,
            ]);

            $this->versions->createDraft($event, $plan, $actor, null, [
                'canvas_width' => $data['canvas_width'] ?? null,
                'canvas_height' => $data['canvas_height'] ?? null,
                'grid_size' => $data['grid_size'] ?? null,
            ]);

            $this->audit->record(
                'floor_plan.created',
                "Floor plan {$plan->name} dibuat pada {$event->name}.",
                $event,
                $actor,
                $plan,
                ['venue_id' => $venue?->id, 'venue_space_id' => $space?->id],
            );

            return $plan->fresh(['versions', 'currentDraft']);
        });
    }

    public function update(Event $event, FloorPlan $plan, array $data, User $actor): FloorPlan
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_MANAGE, 'Anda tidak berwenang mengelola floor plan.');
        $this->assertBelongsToEvent($event, $plan);

        if ($plan->status === FloorPlanStatus::Archived) {
            throw ValidationException::withMessages(['floor_plan' => 'Floor plan yang diarsipkan tidak dapat diubah.']);
        }

        $payload = [];

        if (array_key_exists('name', $data)) {
            $name = $this->requiredName($data['name']);
            $this->assertUniqueName($event, $name, $plan->id);
            $payload['name'] = $name;
        }

        if (array_key_exists('description', $data)) {
            $payload['description'] = $this->plainText($data['description']);
        }

        if (array_key_exists('venue_id', $data) || array_key_exists('venue_space_id', $data)) {
            [$venue, $space] = $this->resolveVenueLinks($event, [
                'venue_id' => $data['venue_id'] ?? $plan->venue_id,
                'venue_space_id' => $data['venue_space_id'] ?? $plan->venue_space_id,
            ]);
            $payload['venue_id'] = $venue?->id;
            $payload['venue_space_id'] = $space?->id;
        }

        return DB::transaction(function () use ($event, $plan, $payload, $actor): FloorPlan {
            $plan->fill($payload)->save();

            $this->audit->record(
                'floor_plan.updated',
                "Floor plan {$plan->name} diperbarui.",
                $event,
                $actor,
                $plan,
                ['changed' => array_keys($payload)],
            );

            return $plan->fresh();
        });
    }

    public function archive(Event $event, FloorPlan $plan, User $actor): FloorPlan
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_MANAGE, 'Anda tidak berwenang mengelola floor plan.');
        $this->assertBelongsToEvent($event, $plan);

        return DB::transaction(function () use ($event, $plan, $actor): FloorPlan {
            $plan->forceFill([
                'status' => FloorPlanStatus::Archived,
                'archived_at' => now(),
            ])->save();

            $this->audit->record('floor_plan.archived', "Floor plan {$plan->name} diarsipkan.", $event, $actor, $plan);

            return $plan->fresh();
        });
    }

    public function delete(Event $event, FloorPlan $plan, User $actor): void
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_DELETE, 'Anda tidak berwenang menghapus floor plan.');
        $this->assertBelongsToEvent($event, $plan);

        if ($plan->versions()->where('status', 'published')->exists()) {
            throw ValidationException::withMessages([
                'floor_plan' => 'Floor plan yang memiliki versi terbit tidak dapat dihapus. Arsipkan sebagai gantinya.',
            ]);
        }

        DB::transaction(function () use ($event, $plan, $actor): void {
            $this->audit->record(
                'floor_plan.deleted',
                "Floor plan {$plan->name} dihapus.",
                $event,
                $actor,
                $plan,
            );

            $plan->delete();
        });
    }

    public function assertBelongsToEvent(Event $event, FloorPlan $plan): void
    {
        abort_unless((int) $plan->event_id === (int) $event->id, 404);
    }

    /**
     * @return array{0: ?Venue, 1: ?VenueSpace}
     */
    protected function resolveVenueLinks(Event $event, array $data): array
    {
        $venue = null;
        $space = null;

        if (! empty($data['venue_id'])) {
            $venue = Venue::query()
                ->where('organization_id', $event->organization_id)
                ->find($data['venue_id']);

            if ($venue === null) {
                throw ValidationException::withMessages(['venue_id' => 'Venue tidak ditemukan pada organisasi ini.']);
            }
        }

        if (! empty($data['venue_space_id'])) {
            $space = VenueSpace::query()
                ->where('organization_id', $event->organization_id)
                ->find($data['venue_space_id']);

            if ($space === null) {
                throw ValidationException::withMessages(['venue_space_id' => 'Ruang venue tidak ditemukan pada organisasi ini.']);
            }

            if ($venue !== null && (int) $space->venue_id !== (int) $venue->id) {
                throw ValidationException::withMessages(['venue_space_id' => 'Ruang tidak berada pada venue yang dipilih.']);
            }

            $venue = $space->venue;
        }

        return [$venue, $space];
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        abort_unless($this->permissions->hasEventPermission($actor, $event, $permission), 403, $message);
    }

    protected function requiredName(mixed $value): string
    {
        $name = trim((string) $value);

        if ($name === '' || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Nama floor plan wajib diisi (maksimal 120 karakter).']);
        }

        return $name;
    }

    protected function assertUniqueName(Event $event, string $name, ?int $ignoreId = null): void
    {
        $exists = FloorPlan::query()
            ->where('event_id', $event->id)
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'Nama floor plan sudah dipakai pada event ini.']);
        }
    }

    protected function plainText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(strip_tags((string) $value));

        return $clean === '' ? null : $clean;
    }
}
