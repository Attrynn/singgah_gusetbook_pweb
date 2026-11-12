<?php

namespace Tests\Feature\M7;

use App\Enums\OrganizationRole;
use App\Enums\VendorArrivalStatus;
use App\Enums\VendorLogisticsResponsibility;
use App\Enums\VendorLogisticsStatus;
use App\Enums\VendorLogisticsType;
use App\Models\EventRunOfShowItem;
use App\Models\EventStation;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\Organization;
use App\Models\User;
use App\Services\VendorLogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorLogisticsTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    private function stationIn($event, string $name = 'Area Katering'): EventStation
    {
        return EventStation::create([
            'event_id' => $event->id,
            'name' => $name,
            'slug' => 'stasiun-'.uniqid(),
            'category' => 'general',
            'status' => 'draft',
        ]);
    }

    public function test_schedule_reference_is_created_with_run_of_show_item(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $item = EventRunOfShowItem::factory()->forEvent($event)->create();
        $station = $this->stationIn($event);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.schedule.store', [$org, $event, $engagement]), [
                'event_run_of_show_item_id' => $item->id,
                'event_station_id' => $station->id,
                'expected_arrival_at' => now()->addDay()->toDateTimeString(),
                'notes' => 'Masuk lewat pintu belakang.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reference = $engagement->scheduleReferences()->firstOrFail();

        $this->assertSame($item->id, (int) $reference->event_run_of_show_item_id);
        $this->assertSame($station->id, (int) $reference->event_station_id);
        $this->assertSame(VendorArrivalStatus::Expected, $reference->arrival_status);
    }

    public function test_station_from_another_event_is_rejected(): void
    {
        $org = $this->organization();
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($eventA, $vendor);

        $foreignStation = $this->stationIn($eventB, 'Stasiun Acara Lain');

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.schedule.store', [$org, $eventA, $engagement]), [
                'event_station_id' => $foreignStation->id,
                'expected_arrival_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('event_station_id');

        $this->assertSame(0, $engagement->scheduleReferences()->count());
    }

    public function test_arrival_transitions_set_timestamps_and_invalid_transitions_are_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $service = app(VendorLogisticsService::class);

        $reference = $service->createSchedule($engagement, [
            'expected_arrival_at' => now()->addHour()->toDateTimeString(),
        ], $owner);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.schedule.arrival', [$org, $event, $reference]), [
                'arrival_status' => VendorArrivalStatus::Arrived->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reference->refresh();
        $this->assertSame(VendorArrivalStatus::Arrived, $reference->arrival_status);
        $this->assertNotNull($reference->arrival_at);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.schedule.arrival', [$org, $event, $reference]), [
                'arrival_status' => VendorArrivalStatus::SetupInProgress->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotNull($reference->refresh()->setup_start_at);

        $invalid = $service->createSchedule($engagement, [
            'expected_arrival_at' => now()->addHour()->toDateTimeString(),
        ], $owner);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.schedule.arrival', [$org, $event, $invalid]), [
                'arrival_status' => VendorArrivalStatus::Departed->value,
            ])
            ->assertSessionHasErrors('arrival_status');

        $this->assertSame(VendorArrivalStatus::Expected, $invalid->refresh()->arrival_status);
    }

    public function test_fulfilling_logistics_requires_a_resolution_note(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $service = app(VendorLogisticsService::class);

        $requirement = $service->createLogistics($engagement, [
            'type' => VendorLogisticsType::Electricity->value,
            'description' => 'Daya 4400 watt untuk booth.',
            'responsibility' => VendorLogisticsResponsibility::Vendor->value,
        ], $owner);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.logistics.status', [$org, $event, $requirement]), [
                'status' => VendorLogisticsStatus::Fulfilled->value,
            ])
            ->assertSessionHasErrors('resolution_note');

        $this->assertSame(VendorLogisticsStatus::Requested, $requirement->refresh()->status);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.logistics.status', [$org, $event, $requirement]), [
                'status' => VendorLogisticsStatus::Fulfilled->value,
                'resolution_note' => 'Genset terpasang dan diuji.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $requirement->refresh();
        $this->assertSame(VendorLogisticsStatus::Fulfilled, $requirement->status);
        $this->assertSame($owner->id, $requirement->fulfilled_by);
        $this->assertNotNull($requirement->fulfilled_at);
    }

    public function test_open_logistics_count_reflects_only_open_requirements(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $service = app(VendorLogisticsService::class);

        foreach (['A', 'B'] as $label) {
            $service->createLogistics($engagement, [
                'type' => VendorLogisticsType::Storage->value,
                'description' => 'Penyimpanan '.$label,
                'responsibility' => VendorLogisticsResponsibility::Organizer->value,
            ], $owner);
        }

        $done = $service->createLogistics($engagement, [
            'type' => VendorLogisticsType::Water->value,
            'description' => 'Air minum',
            'responsibility' => VendorLogisticsResponsibility::Vendor->value,
        ], $owner);

        $service->transitionLogistics($done, VendorLogisticsStatus::Fulfilled, $owner, 'Sudah tersedia.');

        $open = EventVendorLogisticsRequirement::query()
            ->where('event_vendor_engagement_id', $engagement->id)
            ->whereIn('status', [
                VendorLogisticsStatus::Requested->value,
                VendorLogisticsStatus::Acknowledged->value,
                VendorLogisticsStatus::InProgress->value,
            ])
            ->count();

        $this->assertSame(2, $open);
    }

    public function test_invalid_logistics_transition_throws(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $service = app(VendorLogisticsService::class);

        $requirement = $service->createLogistics($engagement, [
            'type' => VendorLogisticsType::LoadingAccess->value,
            'description' => 'Akses bongkar muat.',
            'responsibility' => VendorLogisticsResponsibility::Venue->value,
        ], $owner);

        $service->transitionLogistics($requirement, VendorLogisticsStatus::Cancelled, $owner);

        $this->expectException(ValidationException::class);

        $service->transitionLogistics($requirement->refresh(), VendorLogisticsStatus::InProgress, $owner);
    }
}
