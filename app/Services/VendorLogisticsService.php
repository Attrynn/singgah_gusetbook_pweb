<?php

namespace App\Services;

use App\Enums\VendorArrivalStatus;
use App\Enums\VendorLogisticsStatus;
use App\Models\EventRunOfShowItem;
use App\Models\EventStation;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorScheduleReference;
use App\Models\User;
use App\Models\VendorContact;
use Illuminate\Validation\ValidationException;

/**
 * Vendor schedule references, on-site arrival state and logistics requirements.
 * The M6 run sheet remains the authoritative event schedule; these rows only
 * reference it.
 */
class VendorLogisticsService
{
    public function __construct(private readonly AuditService $audit) {}

    public function createSchedule(EventVendorEngagement $engagement, array $data, User $actor): EventVendorScheduleReference
    {
        $this->assertRunOfShowBelongs($engagement, $data['event_run_of_show_item_id'] ?? null);
        $this->assertStationBelongs($engagement, $data['event_station_id'] ?? null);
        $this->assertContactBelongs($engagement, $data['vendor_contact_id'] ?? null);

        $reference = EventVendorScheduleReference::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'event_run_of_show_item_id' => $data['event_run_of_show_item_id'] ?? null,
            'event_station_id' => $data['event_station_id'] ?? null,
            'vendor_contact_id' => $data['vendor_contact_id'] ?? null,
            'arrival_status' => VendorArrivalStatus::Expected->value,
            'expected_arrival_at' => $data['expected_arrival_at'] ?? null,
            'setup_start_at' => $data['setup_start_at'] ?? null,
            'setup_end_at' => $data['setup_end_at'] ?? null,
            'delivery_start_at' => $data['delivery_start_at'] ?? null,
            'delivery_end_at' => $data['delivery_end_at'] ?? null,
            'service_start_at' => $data['service_start_at'] ?? null,
            'service_end_at' => $data['service_end_at'] ?? null,
            'teardown_start_at' => $data['teardown_start_at'] ?? null,
            'teardown_end_at' => $data['teardown_end_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $actor->id,
        ]);

        $this->audit->record(
            'vendor.schedule.created',
            'Referensi jadwal vendor dibuat.',
            $engagement->event,
            $actor,
            $reference,
        );

        return $reference;
    }

    public function updateSchedule(EventVendorScheduleReference $reference, array $data, User $actor): EventVendorScheduleReference
    {
        $engagement = $reference->engagement;

        $this->assertRunOfShowBelongs($engagement, $data['event_run_of_show_item_id'] ?? null);
        $this->assertStationBelongs($engagement, $data['event_station_id'] ?? null);
        $this->assertContactBelongs($engagement, $data['vendor_contact_id'] ?? null);

        $reference->forceFill(array_intersect_key($data, array_flip([
            'event_run_of_show_item_id', 'event_station_id', 'vendor_contact_id',
            'expected_arrival_at', 'setup_start_at', 'setup_end_at', 'delivery_start_at',
            'delivery_end_at', 'service_start_at', 'service_end_at', 'teardown_start_at',
            'teardown_end_at', 'notes',
        ])))->save();

        $reference->forceFill(['updated_by' => $actor->id])->save();

        $this->audit->record(
            'vendor.schedule.updated',
            'Referensi jadwal vendor diperbarui.',
            $reference->event,
            $actor,
            $reference,
        );

        return $reference->refresh();
    }

    public function transitionArrival(
        EventVendorScheduleReference $reference,
        VendorArrivalStatus $target,
        User $actor,
    ): EventVendorScheduleReference {
        if (! $reference->arrival_status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'arrival_status' => 'Perubahan status kedatangan vendor tidak valid.',
            ]);
        }

        $attributes = [
            'arrival_status' => $target->value,
            'updated_by' => $actor->id,
        ];

        $attributes += match ($target) {
            VendorArrivalStatus::Arrived => ['arrival_at' => now()],
            VendorArrivalStatus::SetupInProgress => ['setup_start_at' => $reference->setup_start_at ?? now()],
            VendorArrivalStatus::Ready => ['ready_at' => now()],
            VendorArrivalStatus::Departed => ['departed_at' => now()],
            default => [],
        };

        $reference->forceFill($attributes)->save();

        $this->audit->recordCritical(
            'vendor.arrival.transitioned',
            "Status kedatangan vendor → {$target->value}",
            $reference->event,
            $actor,
            $reference,
        );

        return $reference->refresh();
    }

    public function createLogistics(EventVendorEngagement $engagement, array $data, User $actor): EventVendorLogisticsRequirement
    {
        $this->assertStationBelongs($engagement, $data['event_station_id'] ?? null);

        $requirement = EventVendorLogisticsRequirement::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'event_station_id' => $data['event_station_id'] ?? null,
            'type' => $data['type'],
            'description' => $data['description'],
            'responsibility' => $data['responsibility'],
            'due_at' => $data['due_at'] ?? null,
            'status' => VendorLogisticsStatus::Requested->value,
        ]);

        $this->audit->record(
            'vendor.logistics.created',
            'Kebutuhan logistik vendor dicatat.',
            $engagement->event,
            $actor,
            $requirement,
        );

        return $requirement;
    }

    public function updateLogistics(EventVendorLogisticsRequirement $requirement, array $data, User $actor): EventVendorLogisticsRequirement
    {
        $this->assertStationBelongs($requirement->engagement, $data['event_station_id'] ?? null);

        $requirement->forceFill(array_intersect_key($data, array_flip([
            'event_station_id', 'type', 'description', 'responsibility', 'due_at',
        ])))->save();

        $this->audit->record(
            'vendor.logistics.updated',
            'Kebutuhan logistik vendor diperbarui.',
            $requirement->event,
            $actor,
            $requirement,
        );

        return $requirement->refresh();
    }

    public function transitionLogistics(
        EventVendorLogisticsRequirement $requirement,
        VendorLogisticsStatus $target,
        User $actor,
        ?string $resolutionNote = null,
    ): EventVendorLogisticsRequirement {
        if (! $requirement->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status logistik tidak valid.',
            ]);
        }

        if (in_array($target, [VendorLogisticsStatus::Fulfilled, VendorLogisticsStatus::Unable], true)
            && trim((string) $resolutionNote) === '') {
            throw ValidationException::withMessages([
                'resolution_note' => 'Catatan penyelesaian wajib diisi.',
            ]);
        }

        $attributes = [
            'status' => $target->value,
            'resolution_note' => $resolutionNote ?? $requirement->resolution_note,
        ];

        if ($target === VendorLogisticsStatus::Fulfilled) {
            $attributes['fulfilled_by'] = $actor->id;
            $attributes['fulfilled_at'] = now();
        }

        $requirement->forceFill($attributes)->save();

        $this->audit->recordCritical(
            'vendor.logistics.transitioned',
            "Status logistik vendor → {$target->value}",
            $requirement->event,
            $actor,
            $requirement,
        );

        return $requirement->refresh();
    }

    protected function assertRunOfShowBelongs(?EventVendorEngagement $engagement, mixed $itemId): void
    {
        if ($itemId === null || $engagement === null) {
            return;
        }

        $exists = EventRunOfShowItem::query()
            ->where('event_id', $engagement->event_id)
            ->whereKey($itemId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'event_run_of_show_item_id' => 'Item run sheet tidak termasuk acara ini.',
            ]);
        }
    }

    protected function assertStationBelongs(?EventVendorEngagement $engagement, mixed $stationId): void
    {
        if ($stationId === null || $engagement === null) {
            return;
        }

        $exists = EventStation::query()
            ->where('event_id', $engagement->event_id)
            ->whereKey($stationId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'event_station_id' => 'Stasiun tidak termasuk acara ini.',
            ]);
        }
    }

    protected function assertContactBelongs(?EventVendorEngagement $engagement, mixed $contactId): void
    {
        if ($contactId === null || $engagement === null) {
            return;
        }

        $exists = VendorContact::query()
            ->where('vendor_id', $engagement->vendor_id)
            ->whereKey($contactId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'vendor_contact_id' => 'Kontak tidak termasuk vendor engagement ini.',
            ]);
        }
    }
}
