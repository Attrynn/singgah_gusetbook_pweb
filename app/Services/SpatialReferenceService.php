<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\FloorPlanObjectType;
use App\Enums\SpatialReferenceStatus;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventRunOfShowItem;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorScheduleReference;
use App\Models\FloorPlanVersion;
use App\Models\SpatialReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Version-aware spatial links between a layout object and an M6/M7 record.
 *
 * The referenced record keeps owning its own domain data; this table only adds
 * "where in the layout" context. No M6 or M7 column is modified.
 */
class SpatialReferenceService
{
    /** @var array<string, class-string<Model>> */
    public const TARGETS = [
        'station' => EventStation::class,
        'task' => EventTask::class,
        'run_of_show_item' => EventRunOfShowItem::class,
        'incident' => EventIncident::class,
        'staff_membership' => EventStaffMembership::class,
        'vendor_engagement' => EventVendorEngagement::class,
        'vendor_schedule_reference' => EventVendorScheduleReference::class,
        'vendor_logistics_requirement' => EventVendorLogisticsRequirement::class,
    ];

    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function attach(Event $event, FloorPlanVersion $version, array $data, User $actor): SpatialReference
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_MANAGE, 'Anda tidak berwenang mengatur referensi spasial.');
        $this->assertVersionBelongsToEvent($event, $version);

        $objectId = trim((string) ($data['object_id'] ?? ''));
        $object = $this->findObject($version, $objectId);

        if ($object === null) {
            throw ValidationException::withMessages(['object_id' => 'Objek tidak ditemukan pada versi layout ini.']);
        }

        $targetType = $this->targetClass($data['referenceable_type'] ?? null);
        $target = $targetType::query()
            ->where('event_id', $event->id)
            ->find($data['referenceable_id'] ?? null);

        if ($target === null) {
            throw ValidationException::withMessages(['referenceable_id' => 'Data tujuan tidak ditemukan pada event ini.']);
        }

        $objectType = FloorPlanObjectType::tryFrom((string) ($data['object_type'] ?? $object['type']));

        if ($objectType === null) {
            throw ValidationException::withMessages(['object_type' => 'Tipe objek tidak terdaftar.']);
        }

        $label = isset($data['label'])
            ? mb_substr(trim(strip_tags((string) $data['label'])), 0, 160)
            : null;

        return DB::transaction(function () use ($event, $version, $actor, $object, $objectType, $targetType, $target, $label): SpatialReference {
            $reference = SpatialReference::query()->updateOrCreate(
                [
                    'referenceable_type' => $targetType,
                    'referenceable_id' => $target->getKey(),
                ],
                [
                    'organization_id' => $event->organization_id,
                    'event_id' => $event->id,
                    'floor_plan_id' => $version->floor_plan_id,
                    'floor_plan_version_id' => $version->id,
                    'object_id' => $object['id'],
                    'object_type' => $objectType->value,
                    'label' => $label,
                    'status' => SpatialReferenceStatus::Active,
                    'created_by' => $actor->id,
                ],
            );

            $this->audit->record(
                'spatial.reference.saved',
                "Referensi spasial disimpan untuk {$targetType} #{$target->getKey()}.",
                $event,
                $actor,
                $reference,
                [
                    'floor_plan_version_id' => $version->id,
                    'object_id' => $object['id'],
                    'referenceable_type' => $targetType,
                    'referenceable_id' => $target->getKey(),
                ],
            );

            return $reference->fresh();
        });
    }

    public function detach(Event $event, SpatialReference $reference, User $actor): void
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::FLOOR_PLAN_MANAGE, 'Anda tidak berwenang mengatur referensi spasial.');
        abort_unless((int) $reference->event_id === (int) $event->id, 404);

        DB::transaction(function () use ($event, $reference, $actor): void {
            $this->audit->record(
                'spatial.reference.removed',
                "Referensi spasial dilepas dari {$reference->referenceable_type} #{$reference->referenceable_id}.",
                $event,
                $actor,
                $reference,
            );

            $reference->delete();
        });
    }

    public function forTarget(Event $event, string $targetType, int $targetId): ?SpatialReference
    {
        $class = $this->targetClass($targetType);

        return SpatialReference::query()
            ->where('event_id', $event->id)
            ->where('referenceable_type', $class)
            ->where('referenceable_id', $targetId)
            ->with('version')
            ->first();
    }

    /**
     * Flag references whose layout object no longer exists in the version.
     */
    public function syncReviewStatus(FloorPlanVersion $version): int
    {
        $objectIds = collect($version->document['objects'] ?? [])->pluck('id')->all();
        $flagged = 0;

        SpatialReference::query()
            ->where('floor_plan_version_id', $version->id)
            ->where('status', SpatialReferenceStatus::Active->value)
            ->each(function (SpatialReference $reference) use ($objectIds, &$flagged): void {
                if (! in_array($reference->object_id, $objectIds, true)) {
                    $reference->forceFill(['status' => SpatialReferenceStatus::NeedsReview])->save();
                    $flagged++;
                }
            });

        return $flagged;
    }

    /**
     * @return class-string<Model>
     */
    public function targetClass(mixed $key): string
    {
        if (! is_string($key) || ! isset(self::TARGETS[$key])) {
            throw ValidationException::withMessages(['referenceable_type' => 'Jenis referensi spasial tidak didukung.']);
        }

        return self::TARGETS[$key];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findObject(FloorPlanVersion $version, string $objectId): ?array
    {
        foreach ($version->document['objects'] ?? [] as $object) {
            if (($object['id'] ?? null) === $objectId) {
                return $object;
            }
        }

        return null;
    }

    protected function assertVersionBelongsToEvent(Event $event, FloorPlanVersion $version): void
    {
        abort_unless((int) $version->event_id === (int) $event->id, 404);
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        abort_unless($this->permissions->hasEventPermission($actor, $event, $permission), 403, $message);
    }
}
