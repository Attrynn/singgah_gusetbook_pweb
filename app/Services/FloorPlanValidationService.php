<?php

namespace App\Services;

use App\Enums\FloorPlanObjectType;
use App\Enums\FloorPlanZoneType;
use App\Enums\SeatingAssignmentStatus;
use App\Models\Event;
use App\Models\FloorPlanVersion;
use App\Models\SeatingAssignment;

/**
 * Advisory layout validation.
 *
 * Errors block publishing; warnings and information never do. Collision checks
 * are intentionally permissive because overlapping objects are frequently
 * deliberate (chairs around a table, objects inside a zone).
 */
class FloorPlanValidationService
{
    private const MAX_ISSUES = 200;

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    public function issues(Event $event, FloorPlanVersion $version): array
    {
        $document = $version->document;
        $canvas = $document['canvas'] ?? [];
        $objects = $document['objects'] ?? [];
        $zones = $document['zones'] ?? [];

        $issues = [];
        $canvasWidth = (float) ($canvas['width'] ?? 0);
        $canvasHeight = (float) ($canvas['height'] ?? 0);

        foreach ($objects as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null) {
                $issues[] = $this->issue('error', 'object.type', 'Objek memiliki tipe yang tidak terdaftar.', $object['id'] ?? null);

                continue;
            }

            $issues = [...$issues, ...$this->geometryIssues($object, $canvasWidth, $canvasHeight)];
        }

        foreach ($zones as $zone) {
            $issues = [...$issues, ...$this->zoneIssues($zone, $canvasWidth, $canvasHeight)];
        }

        $issues = [...$issues, ...$this->capacityIssues($objects)];
        $issues = [...$issues, ...$this->assignmentIssues($event, $version, $objects)];
        $issues = [...$issues, ...$this->collisionIssues($objects)];
        $issues = [...$issues, ...$this->restrictedZoneIssues($objects, $zones)];

        if ($issues === []) {
            $issues[] = $this->issue('info', 'layout.valid', 'Layout tidak memiliki masalah yang terdeteksi.', null);
        }

        return array_slice($issues, 0, self::MAX_ISSUES);
    }

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    protected function geometryIssues(array $object, float $canvasWidth, float $canvasHeight): array
    {
        $x = (float) ($object['x'] ?? 0);
        $y = (float) ($object['y'] ?? 0);
        $width = (float) ($object['width'] ?? 0);
        $height = (float) ($object['height'] ?? 0);

        $fullyOutside = $x + $width <= 0 || $y + $height <= 0 || $x >= $canvasWidth || $y >= $canvasHeight;
        $partiallyOutside = $x < 0 || $y < 0 || $x + $width > $canvasWidth || $y + $height > $canvasHeight;

        if ($fullyOutside) {
            return [$this->issue('error', 'geometry.outside', 'Objek berada sepenuhnya di luar area canvas.', $object['id'] ?? null)];
        }

        if ($partiallyOutside) {
            return [$this->issue('warning', 'geometry.partial', 'Objek sebagian berada di luar area canvas.', $object['id'] ?? null)];
        }

        return [];
    }

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    protected function zoneIssues(array $zone, float $canvasWidth, float $canvasHeight): array
    {
        $geometry = $zone['geometry'] ?? [];
        $zoneId = $zone['id'] ?? null;

        if (($geometry['kind'] ?? null) === 'rect') {
            $x = (float) ($geometry['x'] ?? 0);
            $y = (float) ($geometry['y'] ?? 0);
            $width = (float) ($geometry['width'] ?? 0);
            $height = (float) ($geometry['height'] ?? 0);

            if ($x + $width <= 0 || $y + $height <= 0 || $x >= $canvasWidth || $y >= $canvasHeight) {
                return [$this->issue('warning', 'zone.outside', 'Zona berada di luar area canvas.', $zoneId)];
            }
        }

        if (! FloorPlanZoneType::tryFrom((string) ($zone['type'] ?? '')) instanceof FloorPlanZoneType) {
            return [$this->issue('error', 'zone.type', 'Zona memiliki tipe yang tidak terdaftar.', $zoneId)];
        }

        return [];
    }

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    protected function capacityIssues(array $objects): array
    {
        $chairsPerTable = [];

        foreach ($objects as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null || ! $type->isChair()) {
                continue;
            }

            $tableId = $object['properties']['table_id'] ?? null;

            if (is_string($tableId)) {
                $chairsPerTable[$tableId] = ($chairsPerTable[$tableId] ?? 0) + 1;
            }
        }

        $issues = [];

        foreach ($objects as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null || ! $type->isTable()) {
                continue;
            }

            $capacity = (int) ($object['properties']['capacity'] ?? 0);
            $chairs = $chairsPerTable[$object['id']] ?? 0;

            if ($capacity <= 0) {
                $issues[] = $this->issue('warning', 'capacity.unset', 'Meja belum memiliki kapasitas.', $object['id']);
            } elseif ($chairs !== $capacity) {
                $issues[] = $this->issue(
                    'warning',
                    'capacity.mismatch',
                    "Jumlah kursi ({$chairs}) tidak sesuai kapasitas meja ({$capacity}).",
                    $object['id'],
                );
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    protected function assignmentIssues(Event $event, FloorPlanVersion $version, array $objects): array
    {
        $objectIndex = [];

        foreach ($objects as $object) {
            $objectIndex[$object['id']] = $object;
        }

        $assignments = SeatingAssignment::query()
            ->where('floor_plan_version_id', $version->id)
            ->whereIn('status', [SeatingAssignmentStatus::Active->value, SeatingAssignmentStatus::NeedsReview->value])
            ->get();

        $issues = [];
        $perTable = [];

        foreach ($assignments as $assignment) {
            $table = $objectIndex[$assignment->table_object_id] ?? null;

            if ($table === null) {
                $issues[] = $this->issue(
                    'error',
                    'assignment.table_missing',
                    'Assignment menunjuk meja yang tidak ada pada layout.',
                    $assignment->table_object_id,
                );

                continue;
            }

            if ($assignment->seat_object_id !== null && ! isset($objectIndex[$assignment->seat_object_id])) {
                $issues[] = $this->issue(
                    'error',
                    'assignment.seat_missing',
                    'Assignment menunjuk kursi yang tidak ada pada layout.',
                    $assignment->seat_object_id,
                );
            }

            if ($assignment->status === SeatingAssignmentStatus::Active) {
                $perTable[$assignment->table_object_id] = ($perTable[$assignment->table_object_id] ?? 0) + $assignment->headcount;
            }
        }

        foreach ($perTable as $tableId => $headcount) {
            $capacity = (int) ($objectIndex[$tableId]['properties']['capacity'] ?? 0);

            if ($headcount > $capacity) {
                $issues[] = $this->issue(
                    'error',
                    'assignment.over_capacity',
                    "Assignment meja melebihi kapasitas ({$headcount}/{$capacity}).",
                    $tableId,
                );
            }
        }

        $duplicateMembers = SeatingAssignment::query()
            ->where('event_id', $event->id)
            ->where('status', SeatingAssignmentStatus::Active->value)
            ->whereNotNull('guest_party_membership_id')
            ->select('guest_party_membership_id')
            ->groupBy('guest_party_membership_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('guest_party_membership_id');

        foreach ($duplicateMembers as $memberId) {
            $issues[] = $this->issue(
                'error',
                'assignment.duplicate_member',
                'Satu individu memiliki lebih dari satu assignment seating aktif.',
                null,
            );
        }

        $duplicateSeats = SeatingAssignment::query()
            ->where('floor_plan_version_id', $version->id)
            ->where('status', SeatingAssignmentStatus::Active->value)
            ->whereNotNull('seat_object_id')
            ->select('seat_object_id')
            ->groupBy('seat_object_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('seat_object_id');

        foreach ($duplicateSeats as $seatId) {
            $issues[] = $this->issue('error', 'assignment.duplicate_seat', 'Satu kursi memiliki lebih dari satu assignment aktif.', $seatId);
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    protected function collisionIssues(array $objects): array
    {
        $solid = [];

        foreach ($objects as $object) {
            $type = FloorPlanObjectType::tryFrom((string) ($object['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            if (in_array($type, [
                FloorPlanObjectType::Chair,
                FloorPlanObjectType::Wall,
                FloorPlanObjectType::Door,
                FloorPlanObjectType::Entrance,
                FloorPlanObjectType::Exit,
                FloorPlanObjectType::Aisle,
                FloorPlanObjectType::TextLabel,
                FloorPlanObjectType::Line,
            ], true)) {
                continue;
            }

            $solid[] = $object;
        }

        $solid = array_slice($solid, 0, 500);
        $issues = [];

        for ($i = 0; $i < count($solid); $i++) {
            for ($j = $i + 1; $j < count($solid); $j++) {
                $a = $solid[$i];
                $b = $solid[$j];

                if (($a['group_id'] ?? null) !== null && ($a['group_id'] ?? null) === ($b['group_id'] ?? null)) {
                    continue;
                }

                if ($this->overlaps($a, $b)) {
                    $issues[] = $this->issue(
                        'warning',
                        'spatial.overlap',
                        'Dua objek saling bertumpuk. Pastikan ini disengaja.',
                        $a['id'],
                    );
                }

                if (count($issues) >= self::MAX_ISSUES) {
                    return $issues;
                }
            }
        }

        return $issues;
    }

    /**
     * @return array<int, array{severity: string, code: string, message: string, object_id: ?string}>
     */
    protected function restrictedZoneIssues(array $objects, array $zones): array
    {
        $restricted = array_values(array_filter(
            $zones,
            fn (array $zone): bool => ($zone['type'] ?? null) === FloorPlanZoneType::Restricted->value,
        ));

        if ($restricted === []) {
            return [];
        }

        $operationalTypes = [
            FloorPlanObjectType::BuffetStation->value,
            FloorPlanObjectType::CateringStation->value,
            FloorPlanObjectType::PhotoBooth->value,
            FloorPlanObjectType::VendorStation->value,
            FloorPlanObjectType::StaffStation->value,
        ];

        $issues = [];

        foreach ($objects as $object) {
            if (! in_array($object['type'] ?? null, $operationalTypes, true)) {
                continue;
            }

            foreach ($restricted as $zone) {
                if ($this->insideZone($object, $zone)) {
                    $issues[] = $this->issue(
                        'warning',
                        'spatial.restricted',
                        'Objek operasional berada di dalam area terbatas.',
                        $object['id'],
                    );
                    break;
                }
            }
        }

        return $issues;
    }

    protected function overlaps(array $a, array $b): bool
    {
        $ax1 = (float) $a['x'];
        $ay1 = (float) $a['y'];
        $ax2 = $ax1 + (float) $a['width'];
        $ay2 = $ay1 + (float) $a['height'];

        $bx1 = (float) $b['x'];
        $by1 = (float) $b['y'];
        $bx2 = $bx1 + (float) $b['width'];
        $by2 = $by1 + (float) $b['height'];

        $overlapWidth = min($ax2, $bx2) - max($ax1, $bx1);
        $overlapHeight = min($ay2, $by2) - max($ay1, $by1);

        if ($overlapWidth <= 0 || $overlapHeight <= 0) {
            return false;
        }

        $area = $overlapWidth * $overlapHeight;
        $smallest = min((float) $a['width'] * (float) $a['height'], (float) $b['width'] * (float) $b['height']);

        return $smallest > 0 && ($area / $smallest) > 0.15;
    }

    protected function insideZone(array $object, array $zone): bool
    {
        $geometry = $zone['geometry'] ?? [];

        if (($geometry['kind'] ?? null) !== 'rect') {
            return false;
        }

        $x = (float) ($object['x'] ?? 0);
        $y = (float) ($object['y'] ?? 0);
        $width = (float) ($object['width'] ?? 0);
        $height = (float) ($object['height'] ?? 0);

        return $x >= (float) $geometry['x']
            && $y >= (float) $geometry['y']
            && $x + $width <= (float) $geometry['x'] + (float) $geometry['width']
            && $y + $height <= (float) $geometry['y'] + (float) $geometry['height'];
    }

    /**
     * @return array{severity: string, code: string, message: string, object_id: ?string}
     */
    protected function issue(string $severity, string $code, string $message, ?string $objectId): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'message' => $message,
            'object_id' => $objectId,
        ];
    }
}
