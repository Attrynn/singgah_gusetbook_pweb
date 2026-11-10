<?php

namespace Tests\Feature\M6;

use App\Enums\EventStaffRole;
use App\Enums\GuestPartyStatus;
use App\Enums\OrganizationRole;
use App\Enums\ReadinessStatus;
use App\Enums\StationStatus;
use App\Models\EventIncident;
use App\Models\EventRunOfShowItem;
use App\Models\EventSetupConfig;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\EventTask;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\Organization;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\OperationsDashboardService;
use App\Services\OperationsReadinessService;
use App\Services\OperationsReportService;
use Database\Factories\GuestPassFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class OperationsReadinessAndReportingTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private OperationsReadinessService $readiness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->readiness = app(OperationsReadinessService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    private function readyEvent(Organization $organization): array
    {
        $event = $this->eventIn($organization);
        $supervisor = $this->staffOf($organization, $event, EventStaffRole::StationSupervisor);
        $checkin = $this->staffOf($organization, $event, EventStaffRole::CheckinStaff);

        EventSetupConfig::create([
            'event_id' => $event->id,
            'primary_pic_staff_membership_id' => $supervisor->id,
            'setup_status' => 'ready',
        ]);

        $station = EventStation::create([
            'event_id' => $event->id,
            'name' => 'Registrasi',
            'slug' => 'registrasi',
            'category' => 'registration',
            'status' => StationStatus::Ready->value,
            'supervisor_membership_id' => $supervisor->id,
        ]);

        EventStationAssignment::create([
            'event_id' => $event->id,
            'station_id' => $station->id,
            'staff_membership_id' => $supervisor->id,
            'is_supervisor' => true,
            'assigned_at' => now(),
        ]);

        EventRunOfShowItem::create([
            'event_id' => $event->id,
            'title' => 'Akad',
            'status' => 'pending',
            'sequence' => 1,
            'planned_start' => now()->addHour(),
            'planned_end' => now()->addHours(2),
        ]);

        $party = Guest::factory()->create(['event_id' => $event->id]);
        $member = GuestPartyMembership::create([
            'guest_id' => $party->id,
            'event_id' => $event->id,
            'name' => 'Tamu',
            'status' => GuestPartyStatus::Active->value,
        ]);

        GuestPassFactory::new()->create([
            'guest_party_membership_id' => $member->id,
            'event_id' => $event->id,
            'status' => 'active',
        ]);

        return [$event, $supervisor, $checkin];
    }

    public function test_missing_venue_blocks_readiness(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org, ['venue_name' => '']);
        $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $assessment = $this->readiness->assess($event);

        $this->assertSame(ReadinessStatus::NotReady, $assessment['status']);
        $this->assertSame('event_configuration', collect($assessment['items'])->firstWhere('status', ReadinessStatus::NotReady)['key']);
    }

    public function test_absence_of_staff_blocks_readiness(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);

        $assessment = $this->readiness->assess($event);

        $this->assertSame(ReadinessStatus::NotReady, $assessment['status']);
        $this->assertTrue(collect($assessment['items'])->contains(
            fn (array $item) => $item['key'] === 'staff_coverage' && $item['status'] === ReadinessStatus::NotReady,
        ));
    }

    public function test_optional_gaps_produce_warnings_not_blockers(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $assessment = $this->readiness->assess($event);

        $this->assertSame(ReadinessStatus::Warning, $assessment['status']);
        $this->assertSame(0, $assessment['blocking']);
        $this->assertGreaterThan(0, $assessment['warnings']);
    }

    public function test_overdue_critical_task_blocks_readiness(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        EventTask::create([
            'event_id' => $event->id,
            'title' => 'Tugas kritis',
            'priority' => 'critical',
            'status' => 'open',
            'due_at' => now()->subHour(),
        ]);

        $assessment = $this->readiness->assess($event);

        $this->assertSame(ReadinessStatus::NotReady, $assessment['status']);
        $this->assertTrue(collect($assessment['items'])->contains(
            fn (array $item) => $item['key'] === 'critical_tasks' && $item['status'] === ReadinessStatus::NotReady,
        ));
    }

    public function test_checkin_readiness_is_not_applicable_without_active_passes(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $assessment = $this->readiness->assess($event);

        $checkin = collect($assessment['items'])->firstWhere('key', 'checkin_readiness');

        $this->assertSame(ReadinessStatus::NotApplicable, $checkin['status']);
    }

    public function test_fully_prepared_event_is_ready(): void
    {
        $org = $this->organization();
        [$event] = $this->readyEvent($org);

        $assessment = $this->readiness->assess($event);

        $this->assertSame(ReadinessStatus::Ready, $assessment['status'], json_encode($assessment['items']));
        $this->assertSame(0, $assessment['blocking']);
        $this->assertSame(0, $assessment['warnings']);
    }

    public function test_dashboard_summary_counts_persisted_operational_records(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        [$event] = $this->readyEvent($org);

        EventTask::create([
            'event_id' => $event->id,
            'title' => 'Tugas terbuka',
            'priority' => 'high',
            'status' => 'open',
        ]);
        EventTask::create([
            'event_id' => $event->id,
            'title' => 'Tugas selesai',
            'priority' => 'low',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        EventIncident::create([
            'event_id' => $event->id,
            'title' => 'Insiden terbuka',
            'category' => 'technical',
            'severity' => 'high',
            'status' => 'open',
            'reported_at' => now(),
        ]);

        $summary = app(OperationsDashboardService::class)->summary($event, $owner);

        $this->assertSame(2, $summary['staff']['active']);
        $this->assertSame(1, $summary['stations']['total']);
        $this->assertSame(1, $summary['stations']['ready']);
        $this->assertSame(1, $summary['run_sheet']['total']);
        $this->assertSame(2, $summary['tasks']['total']);
        $this->assertSame(1, $summary['tasks']['open']);
        $this->assertSame(1, $summary['tasks']['completed']);
        $this->assertSame(1, $summary['incidents']['open']);
        $this->assertSame(1, $summary['incidents']['high']);
        $this->assertSame('draft', $summary['operation']['status']->value);
    }

    public function test_dashboard_reuses_m5_attendance_metrics_without_recomputing_them(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        [$event] = $this->readyEvent($org);

        $expected = app(AttendanceService::class)->dashboardStats($event);
        $summary = app(OperationsDashboardService::class)->summary($event, $owner);

        $this->assertSame($expected['guests'], $summary['attendance']['guests']);
        $this->assertSame($expected['passes'], $summary['attendance']['passes']);
        $this->assertSame($expected['checkins'], $summary['attendance']['checkins']);
    }

    public function test_staff_report_exposes_contact_only_when_requested(): void
    {
        $org = $this->organization();
        [$event] = $this->readyEvent($org);

        $reports = app(OperationsReportService::class);

        $withoutContact = $reports->staffReport($event, false);
        $withContact = $reports->staffReport($event, true);

        $this->assertNotContains('Email', $withoutContact['headers']);
        $this->assertContains('Email', $withContact['headers']);
        $this->assertCount(count($withoutContact['headers']), $withoutContact['rows'][0]);
        $this->assertCount(count($withContact['headers']), $withContact['rows'][0]);
    }

    public function test_attendance_report_labels_legacy_rsvp_metric_explicitly(): void
    {
        $org = $this->organization();
        [$event] = $this->readyEvent($org);

        $report = app(OperationsReportService::class)->attendanceReport($event);

        $labels = array_column($report['rows'], 0);

        $this->assertContains('Jumlah kehadiran RSVP (legacy)', $labels);
        $this->assertContains('Pass aktif', $labels);
    }

    public function test_all_report_types_have_matching_headers_and_rows(): void
    {
        $org = $this->organization();
        $owner = $this->owner($org);
        [$event] = $this->readyEvent($org);

        $reports = app(OperationsReportService::class)->all($event, $owner);

        $this->assertSame(
            ['staff', 'stations', 'run_sheet', 'tasks', 'incidents', 'attendance'],
            array_keys($reports),
        );

        foreach ($reports as $key => $report) {
            $this->assertNotEmpty($report['headers'], $key);
            $this->assertNotEmpty($report['filename'], $key);

            foreach ($report['rows'] as $row) {
                $this->assertCount(count($report['headers']), $row, $key);
            }
        }
    }

    public function test_event_from_another_organization_is_isolated_in_reports(): void
    {
        $org = $this->organization('one');
        $other = $this->organization('two');
        [$event] = $this->readyEvent($org);
        $foreignEvent = $this->eventIn($other);

        $reports = app(OperationsReportService::class);
        $staffRows = $reports->staffReport($foreignEvent)['rows'];

        $this->assertSame([], $staffRows);
        $this->assertNotSame($event->id, $foreignEvent->id);
    }
}
