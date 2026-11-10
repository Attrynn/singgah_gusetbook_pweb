<?php

namespace Tests\Feature\M6;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Enums\StationStatus;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\Organization;
use App\Models\User;
use App\Services\StationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class StationOperationsTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private StationService $stations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stations = app(StationService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_creating_a_station_requires_management_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->expectException(HttpException::class);

        $this->stations->create($event, ['name' => 'Registrasi'], $general->user);
    }

    public function test_station_names_generate_unique_slugs_within_an_event(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $first = $this->stations->create($event, ['name' => 'Registrasi'], $owner);
        $second = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->assertSame('registrasi', $first->slug);
        $this->assertSame('registrasi-2', $second->slug);
    }

    public function test_unknown_station_category_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->expectException(ValidationException::class);

        $this->stations->create($event, ['name' => 'Pos', 'category' => 'not-a-category'], $owner);
    }

    public function test_supervisor_must_be_an_active_member_of_the_same_event(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->staffOf($org, $other, EventStaffRole::StationSupervisor);

        $this->expectException(ValidationException::class);

        $this->stations->create($event, [
            'name' => 'Registrasi',
            'supervisor_membership_id' => $foreign->id,
        ], $owner);
    }

    public function test_invalid_station_status_transition_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->expectException(ValidationException::class);

        $this->stations->transition($event, $station, StationStatus::Active, $owner);
    }

    public function test_station_can_transition_from_draft_to_ready(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $ready = $this->stations->transition($event, $station, StationStatus::Ready, $owner);

        $this->assertSame(StationStatus::Ready, $ready->status);
    }

    public function test_station_with_active_staff_cannot_be_deleted(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->stations->assignStaff($event, $station, $staff, $owner);

        $this->expectException(ValidationException::class);

        $this->stations->delete($event, $station, $owner);
    }

    public function test_operational_station_cannot_be_deleted(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);
        $this->stations->transition($event, $station, StationStatus::Ready, $owner);

        $this->expectException(ValidationException::class);

        $this->stations->delete($event, $station->fresh(), $owner);
    }

    public function test_draft_station_without_staff_can_be_deleted(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->stations->delete($event, $station, $owner);

        $this->assertDatabaseMissing('event_stations', ['id' => $station->id]);
    }

    public function test_inactive_staff_cannot_be_assigned_to_a_station(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $suspended = $this->staffOf($org, $event, EventStaffRole::GeneralStaff, MembershipStatus::Suspended);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->expectException(ValidationException::class);

        $this->stations->assignStaff($event, $station, $suspended, $owner);
    }

    public function test_unassigning_the_supervisor_clears_the_station_supervisor(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::StationSupervisor);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->stations->assignStaff($event, $station, $staff, $owner, true);
        $this->assertSame($staff->id, $station->fresh()->supervisor_membership_id);

        $this->stations->unassignStaff($event, $station->fresh(), $staff, $owner);

        $this->assertNull($station->fresh()->supervisor_membership_id);
        $this->assertNotNull(
            EventStationAssignment::query()->where('station_id', $station->id)->first()?->removed_at,
        );
    }

    public function test_setting_a_supervisor_marks_the_assignment_as_supervisory(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::StationSupervisor);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->stations->setSupervisor($event, $station, $staff, $owner);

        $assignment = EventStationAssignment::query()
            ->where('station_id', $station->id)
            ->where('staff_membership_id', $staff->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertTrue($assignment->is_supervisor);
    }

    public function test_coverage_progresses_from_no_staff_to_ready(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $staff = $this->staffOf($org, $event, EventStaffRole::StationSupervisor);
        $station = $this->stations->create($event, ['name' => 'Registrasi'], $owner);

        $this->assertSame('no_staff', $this->stations->coverage($station)['level']);

        $this->stations->assignStaff($event, $station, $staff, $owner);
        $this->assertSame('no_supervisor', $this->stations->coverage($station->fresh())['level']);

        $this->stations->setSupervisor($event, $station->fresh(), $staff, $owner);
        $this->stations->transition($event, $station->fresh(), StationStatus::Ready, $owner);

        $this->assertSame('ready', $this->stations->coverage($station->fresh())['level']);
    }

    public function test_station_from_another_event_is_not_editable(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->stations->create($other, ['name' => 'Registrasi'], $owner);

        $this->expectException(HttpException::class);

        $this->stations->update($event, $foreign, ['name' => 'Hijack'], $owner);
    }

    public function test_station_records_are_scoped_to_their_event(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->stations->create($event, ['name' => 'Registrasi'], $owner);
        $this->stations->create($other, ['name' => 'Registrasi'], $owner);

        $this->assertSame(1, EventStation::query()->where('event_id', $event->id)->count());
    }
}
