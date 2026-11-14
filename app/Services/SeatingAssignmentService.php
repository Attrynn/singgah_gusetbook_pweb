<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\FloorPlanObjectType;
use App\Enums\SeatingAssignmentStatus;
use App\Models\Event;
use App\Models\FloorPlanVersion;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\SeatingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Guest-to-table and guest-to-seat assignments.
 *
 * Individuals are seated through `guest_party_memberships`; a party without
 * identified members is seated as a headcount placeholder so M5 identity is
 * never duplicated or fabricated.
 */
class SeatingAssignmentService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
    ) {}

    public function assign(Event $event, FloorPlanVersion $version, array $data, User $actor): SeatingAssignment
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::SEATING_ASSIGN, 'Anda tidak berwenang mengatur seating.');
        $this->assertVersionBelongsToEvent($event, $version);

        if (! $version->isDraft()) {
            throw ValidationException::withMessages([
                'version' => 'Seating hanya dapat diubah pada draf layout.',
            ]);
        }

        $tableId = $this->requiredObjectId($data['table_object_id'] ?? null, 'Meja wajib dipilih.');
        $table = $this->findObject($version, $tableId);

        if ($table === null || ! FloorPlanObjectType::from($table['type'])->isTable()) {
            throw ValidationException::withMessages(['table_object_id' => 'Meja tidak ditemukan pada layout ini.']);
        }

        $seatId = $data['seat_object_id'] ?? null;

        if ($seatId !== null) {
            $seat = $this->findObject($version, (string) $seatId);

            if ($seat === null || ! FloorPlanObjectType::from($seat['type'])->isChair()) {
                throw ValidationException::withMessages(['seat_object_id' => 'Kursi tidak ditemukan pada layout ini.']);
            }

            if (($seat['properties']['table_id'] ?? null) !== $tableId) {
                throw ValidationException::withMessages(['seat_object_id' => 'Kursi tidak berada pada meja yang dipilih.']);
            }
        }

        $guest = Guest::query()
            ->where('event_id', $event->id)
            ->find($data['guest_id'] ?? null);

        if ($guest === null) {
            throw ValidationException::withMessages(['guest_id' => 'Tamu tidak ditemukan pada event ini.']);
        }

        $member = null;

        if (! empty($data['guest_party_membership_id'])) {
            $member = GuestPartyMembership::query()
                ->where('event_id', $event->id)
                ->where('guest_id', $guest->id)
                ->find($data['guest_party_membership_id']);

            if ($member === null) {
                throw ValidationException::withMessages(['guest_party_membership_id' => 'Individu tamu tidak ditemukan pada party ini.']);
            }
        }

        $headcount = $member !== null ? 1 : (int) ($data['headcount'] ?? 1);

        if ($headcount < 1 || $headcount > 100) {
            throw ValidationException::withMessages(['headcount' => 'Jumlah orang pada assignment tidak valid.']);
        }

        if ($member === null && $headcount > (int) $guest->max_attendees) {
            throw ValidationException::withMessages([
                'headcount' => 'Jumlah orang melebihi kuota undangan party ini.',
            ]);
        }

        $capacity = (int) ($table['properties']['capacity'] ?? 0);

        if ($capacity <= 0) {
            throw ValidationException::withMessages([
                'table_object_id' => 'Meja belum memiliki kapasitas. Atur kapasitas meja terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($event, $version, $actor, $tableId, $seatId, $guest, $member, $headcount, $capacity): SeatingAssignment {
            $activeForTable = SeatingAssignment::query()
                ->where('floor_plan_version_id', $version->id)
                ->where('table_object_id', $tableId)
                ->where('status', SeatingAssignmentStatus::Active->value)
                ->lockForUpdate()
                ->get();

            if ($activeForTable->sum('headcount') + $headcount > $capacity) {
                throw ValidationException::withMessages([
                    'table_object_id' => 'Kapasitas meja tidak mencukupi untuk assignment ini.',
                ]);
            }

            $duplicate = SeatingAssignment::query()
                ->where('event_id', $event->id)
                ->where('status', SeatingAssignmentStatus::Active->value)
                ->when(
                    $member !== null,
                    fn ($query) => $query->where('guest_party_membership_id', $member->id),
                    fn ($query) => $query->whereNull('guest_party_membership_id')->where('guest_id', $guest->id),
                )
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'guest_id' => 'Tamu ini sudah memiliki assignment seating aktif pada event ini.',
                ]);
            }

            if ($seatId !== null) {
                $seatTaken = SeatingAssignment::query()
                    ->where('floor_plan_version_id', $version->id)
                    ->where('seat_object_id', $seatId)
                    ->where('status', SeatingAssignmentStatus::Active->value)
                    ->lockForUpdate()
                    ->exists();

                if ($seatTaken) {
                    throw ValidationException::withMessages(['seat_object_id' => 'Kursi ini sudah ditempati.']);
                }
            }

            $assignment = SeatingAssignment::create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'floor_plan_id' => $version->floor_plan_id,
                'floor_plan_version_id' => $version->id,
                'table_object_id' => $tableId,
                'seat_object_id' => $seatId,
                'guest_id' => $guest->id,
                'guest_party_membership_id' => $member?->id,
                'headcount' => $headcount,
                'status' => SeatingAssignmentStatus::Active,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);

            $this->audit->record(
                'seating.assignment.created',
                "Tamu {$guest->name} ditempatkan pada meja {$tableId}.",
                $event,
                $actor,
                $assignment,
                [
                    'floor_plan_version_id' => $version->id,
                    'table_object_id' => $tableId,
                    'seat_object_id' => $seatId,
                    'guest_party_membership_id' => $member?->id,
                    'headcount' => $headcount,
                ],
            );

            return $assignment->fresh();
        });
    }

    public function release(Event $event, SeatingAssignment $assignment, ?string $reason, User $actor): SeatingAssignment
    {
        $this->assertActorHas($event, $actor, PermissionRegistry::SEATING_ASSIGN, 'Anda tidak berwenang mengatur seating.');
        $this->assertAssignmentBelongsToEvent($event, $assignment);

        if ($assignment->status !== SeatingAssignmentStatus::Active) {
            return $assignment;
        }

        return DB::transaction(function () use ($event, $assignment, $reason, $actor): SeatingAssignment {
            $assignment->forceFill([
                'status' => SeatingAssignmentStatus::Released,
                'released_at' => now(),
                'release_reason' => $reason !== null ? mb_substr(trim(strip_tags($reason)), 0, 255) : null,
            ])->save();

            $this->audit->record(
                'seating.assignment.released',
                "Assignment seating tamu {$assignment->guest->name} dilepas.",
                $event,
                $actor,
                $assignment,
                ['reason' => $reason],
            );

            return $assignment->fresh();
        });
    }

    /**
     * @return Collection<int, SeatingAssignment>
     */
    public function forVersion(Event $event, FloorPlanVersion $version): Collection
    {
        $this->assertVersionBelongsToEvent($event, $version);

        return SeatingAssignment::query()
            ->where('floor_plan_version_id', $version->id)
            ->where('status', SeatingAssignmentStatus::Active->value)
            ->with(['guest', 'member'])
            ->orderBy('table_object_id')
            ->get();
    }

    /**
     * @return array<string, array{capacity: int, assigned: int, remaining: int, chairs: int}>
     */
    public function tableSummary(FloorPlanVersion $version): array
    {
        $summary = [];

        foreach ($version->document['objects'] ?? [] as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null || ! $type->isTable()) {
                continue;
            }

            $summary[$object['id']] = [
                'capacity' => (int) ($object['properties']['capacity'] ?? 0),
                'assigned' => 0,
                'remaining' => (int) ($object['properties']['capacity'] ?? 0),
                'chairs' => 0,
            ];
        }

        foreach ($version->document['objects'] ?? [] as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null || ! $type->isChair()) {
                continue;
            }

            $tableId = $object['properties']['table_id'] ?? null;

            if (is_string($tableId) && isset($summary[$tableId])) {
                $summary[$tableId]['chairs']++;
            }
        }

        $assignments = SeatingAssignment::query()
            ->where('floor_plan_version_id', $version->id)
            ->where('status', SeatingAssignmentStatus::Active->value)
            ->get()
            ->groupBy('table_object_id');

        foreach ($assignments as $tableId => $rows) {
            if (! isset($summary[$tableId])) {
                continue;
            }

            $assigned = (int) $rows->sum('headcount');
            $summary[$tableId]['assigned'] = $assigned;
            $summary[$tableId]['remaining'] = max(0, $summary[$tableId]['capacity'] - $assigned);
        }

        return $summary;
    }

    /**
     * Flag assignments whose table or seat no longer exists in the version.
     */
    public function syncReviewStatus(Event $event, FloorPlanVersion $version): int
    {
        $this->assertVersionBelongsToEvent($event, $version);

        $objectIds = collect($version->document['objects'] ?? [])->pluck('id')->all();
        $flagged = 0;

        SeatingAssignment::query()
            ->where('floor_plan_version_id', $version->id)
            ->where('status', SeatingAssignmentStatus::Active->value)
            ->each(function (SeatingAssignment $assignment) use ($objectIds, &$flagged): void {
                $missingTable = ! in_array($assignment->table_object_id, $objectIds, true);
                $missingSeat = $assignment->seat_object_id !== null
                    && ! in_array($assignment->seat_object_id, $objectIds, true);

                if ($missingTable || $missingSeat) {
                    $assignment->forceFill(['status' => SeatingAssignmentStatus::NeedsReview])->save();
                    $flagged++;
                }
            });

        return $flagged;
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

    protected function requiredObjectId(mixed $value, string $message): string
    {
        $id = trim((string) $value);

        if ($id === '' || ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id)) {
            throw ValidationException::withMessages(['table_object_id' => $message]);
        }

        return $id;
    }

    public function assertVersionBelongsToEvent(Event $event, FloorPlanVersion $version): void
    {
        abort_unless((int) $version->event_id === (int) $event->id, 404);
    }

    public function assertAssignmentBelongsToEvent(Event $event, SeatingAssignment $assignment): void
    {
        abort_unless((int) $assignment->event_id === (int) $event->id, 404);
    }

    protected function assertActorHas(Event $event, User $actor, string $permission, string $message): void
    {
        abort_unless($this->permissions->hasEventPermission($actor, $event, $permission), 403, $message);
    }
}
