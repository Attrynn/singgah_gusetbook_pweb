<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\FloorPlanObjectType;
use App\Enums\FloorPlanVersionStatus;
use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\FloorPlanVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Version lifecycle for floor plans.
 *
 * A version is the unit of immutability: drafts are editable, published
 * versions are frozen snapshots, and editing a published layout always starts a
 * new draft instead of mutating history.
 */
class FloorPlanVersionService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
        protected FloorPlanDocumentValidator $validator,
        protected FloorPlanValidationService $layoutValidator,
    ) {}

    public function createDraft(
        Event $event,
        FloorPlan $plan,
        User $actor,
        ?FloorPlanVersion $source = null,
        array $canvasOverrides = [],
    ): FloorPlanVersion {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_VERSION, 'Anda tidak berwenang membuat versi floor plan.');
        $this->assertPlanBelongsToEvent($event, $plan);

        if ($source !== null) {
            $this->assertVersionBelongsToEvent($event, $source);
        }

        $hasDraft = $plan->versions()->where('status', FloorPlanVersionStatus::Draft->value)->exists();

        if ($hasDraft) {
            throw ValidationException::withMessages([
                'version' => 'Floor plan masih memiliki draf aktif. Terbitkan atau arsipkan draf tersebut terlebih dahulu.',
            ]);
        }

        $document = $source?->document ?? $this->blankDocument($canvasOverrides);
        $document = $this->validator->validate($document);
        $stats = $this->stats($document);
        $canvas = $document['canvas'];

        return DB::transaction(function () use ($event, $plan, $actor, $document, $stats, $canvas, $source): FloorPlanVersion {
            $versionNumber = (int) $plan->versions()->max('version_number') + 1;

            $version = FloorPlanVersion::create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'floor_plan_id' => $plan->id,
                'version_number' => $versionNumber,
                'status' => FloorPlanVersionStatus::Draft,
                'schema_version' => FloorPlanDocumentValidator::SCHEMA_VERSION,
                'canvas_width' => $canvas['width'],
                'canvas_height' => $canvas['height'],
                'unit' => $canvas['unit'],
                'grid_size' => $canvas['grid_size'],
                'grid_visible' => $canvas['grid_visible'],
                'snap_enabled' => $canvas['snap_enabled'],
                'background_color' => $canvas['background_color'],
                'document' => $document,
                'object_count' => $stats['object_count'],
                'table_count' => $stats['table_count'],
                'chair_count' => $stats['chair_count'],
                'zone_count' => $stats['zone_count'],
                'seat_capacity' => $stats['seat_capacity'],
                'lock_version' => 0,
                'created_by' => $actor->id,
            ]);

            $this->audit->record(
                'floor_plan.version.created',
                "Draf versi {$versionNumber} dibuat untuk floor plan {$plan->name}.",
                $event,
                $actor,
                $version,
                ['source_version_id' => $source?->id],
            );

            return $version->fresh();
        });
    }

    public function createNextDraft(Event $event, FloorPlan $plan, User $actor): FloorPlanVersion
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_VERSION, 'Anda tidak berwenang membuat versi floor plan.');

        $source = $plan->versions()
            ->where('status', FloorPlanVersionStatus::Published->value)
            ->orderByDesc('version_number')
            ->first();

        if ($source === null) {
            throw ValidationException::withMessages([
                'version' => 'Belum ada versi terbit yang dapat dijadikan dasar draf baru.',
            ]);
        }

        return $this->createDraft($event, $plan, $actor, $source);
    }

    public function saveDocument(
        Event $event,
        FloorPlanVersion $version,
        array $document,
        int $lockVersion,
        User $actor,
    ): FloorPlanVersion {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_MANAGE, 'Anda tidak berwenang mengubah floor plan.');
        $this->assertVersionBelongsToEvent($event, $version);

        if (! $version->isDraft()) {
            throw ValidationException::withMessages([
                'version' => 'Versi yang sudah diterbitkan bersifat permanen. Buat draf baru untuk melakukan perubahan.',
            ]);
        }

        if ((int) $version->lock_version !== $lockVersion) {
            abort(409, 'Dokumen telah berubah di perangkat lain. Muat ulang sebelum menyimpan.');
        }

        $normalized = $this->validator->validate($document);
        $stats = $this->stats($normalized);
        $canvas = $normalized['canvas'];

        return DB::transaction(function () use ($version, $normalized, $stats, $canvas): FloorPlanVersion {
            $version->forceFill([
                'document' => $normalized,
                'canvas_width' => $canvas['width'],
                'canvas_height' => $canvas['height'],
                'unit' => $canvas['unit'],
                'grid_size' => $canvas['grid_size'],
                'grid_visible' => $canvas['grid_visible'],
                'snap_enabled' => $canvas['snap_enabled'],
                'background_color' => $canvas['background_color'],
                'object_count' => $stats['object_count'],
                'table_count' => $stats['table_count'],
                'chair_count' => $stats['chair_count'],
                'zone_count' => $stats['zone_count'],
                'seat_capacity' => $stats['seat_capacity'],
                'lock_version' => $version->lock_version + 1,
            ])->save();

            return $version->fresh();
        });
    }

    public function publish(Event $event, FloorPlanVersion $version, User $actor): FloorPlanVersion
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_PUBLISH, 'Anda tidak berwenang menerbitkan floor plan.');
        $this->assertVersionBelongsToEvent($event, $version);

        if (! $version->isDraft()) {
            throw ValidationException::withMessages(['version' => 'Hanya draf yang dapat diterbitkan.']);
        }

        $issues = $this->layoutValidator->issues($event, $version);
        $errors = array_values(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'error'));

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'document' => array_map(fn (array $issue): string => $issue['message'], $errors),
            ]);
        }

        return DB::transaction(function () use ($event, $version, $actor): FloorPlanVersion {
            $version->forceFill([
                'status' => FloorPlanVersionStatus::Published,
                'published_by' => $actor->id,
                'published_at' => now(),
                'lock_version' => $version->lock_version + 1,
            ])->save();

            $this->audit->recordCritical(
                'floor_plan.version.published',
                "Versi {$version->version_number} floor plan {$version->floorPlan->name} diterbitkan.",
                $event,
                $actor,
                $version,
                ['version_number' => $version->version_number],
            );

            return $version->fresh();
        });
    }

    public function archive(Event $event, FloorPlanVersion $version, User $actor): FloorPlanVersion
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_VERSION, 'Anda tidak berwenang mengarsipkan versi floor plan.');
        $this->assertVersionBelongsToEvent($event, $version);

        if ($version->isArchived()) {
            return $version;
        }

        return DB::transaction(function () use ($event, $version, $actor): FloorPlanVersion {
            $version->forceFill([
                'status' => FloorPlanVersionStatus::Archived,
                'archived_by' => $actor->id,
                'archived_at' => now(),
                'lock_version' => $version->lock_version + 1,
            ])->save();

            $this->audit->record(
                'floor_plan.version.archived',
                "Versi {$version->version_number} floor plan {$version->floorPlan->name} diarsipkan.",
                $event,
                $actor,
                $version,
                ['version_number' => $version->version_number],
            );

            return $version->fresh();
        });
    }

    /**
     * @return array{object_count: int, table_count: int, chair_count: int, zone_count: int, seat_capacity: int}
     */
    public function stats(array $document): array
    {
        $tableCount = 0;
        $chairCount = 0;
        $seatCapacity = 0;

        foreach ($document['objects'] ?? [] as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            if ($type->isTable()) {
                $tableCount++;
                $seatCapacity += (int) ($object['properties']['capacity'] ?? 0);
            }

            if ($type->isChair()) {
                $chairCount++;
            }
        }

        return [
            'object_count' => count($document['objects'] ?? []),
            'table_count' => $tableCount,
            'chair_count' => $chairCount,
            'zone_count' => count($document['zones'] ?? []),
            'seat_capacity' => $seatCapacity,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function blankDocument(array $overrides = []): array
    {
        return [
            'schema_version' => FloorPlanDocumentValidator::SCHEMA_VERSION,
            'canvas' => [
                'width' => (float) ($overrides['canvas_width'] ?? 2000),
                'height' => (float) ($overrides['canvas_height'] ?? 1500),
                'unit' => 'cm',
                'grid_size' => (float) ($overrides['grid_size'] ?? 50),
                'grid_visible' => true,
                'snap_enabled' => true,
                'background_color' => '#ffffff',
            ],
            'objects' => [],
            'zones' => [],
        ];
    }

    public function assertVersionBelongsToEvent(Event $event, FloorPlanVersion $version): void
    {
        abort_unless((int) $version->event_id === (int) $event->id, 404);
    }

    protected function assertPlanBelongsToEvent(Event $event, FloorPlan $plan): void
    {
        abort_unless((int) $plan->event_id === (int) $event->id, 404);
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        abort_unless($this->permissions->hasEventPermission($actor, $event, $permission), 403, $message);
    }
}
