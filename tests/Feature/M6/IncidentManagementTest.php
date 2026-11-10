<?php

namespace Tests\Feature\M6;

use App\Enums\EventStaffRole;
use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\OrganizationRole;
use App\Models\EventIncident;
use App\Models\Organization;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class IncidentManagementTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private IncidentService $incidents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->incidents = app(IncidentService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_reporting_an_incident_requires_the_create_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $observer = $this->staffOf($org, $event, EventStaffRole::Observer);

        $this->expectException(HttpException::class);

        $this->incidents->report($event, ['title' => 'Insiden'], $observer->user);
    }

    public function test_unknown_incident_category_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->expectException(ValidationException::class);

        $this->incidents->report($event, ['title' => 'Insiden', 'category' => 'nope'], $owner);
    }

    public function test_reporting_records_initial_history_entry(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $incident = $this->incidents->report($event, [
            'title' => 'Proyektor mati',
            'category' => IncidentCategory::Technical->value,
            'severity' => IncidentSeverity::High->value,
        ], $owner);

        $this->assertSame(IncidentStatus::Open, $incident->status);
        $this->assertSame(IncidentSeverity::High, $incident->severity);
        $this->assertSame(1, $incident->updates()->count());
    }

    public function test_incident_can_only_be_assigned_to_a_manager(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);

        $this->expectException(ValidationException::class);

        $this->incidents->assign($event, $incident, $general->user, $owner);
    }

    public function test_unassigned_staff_cannot_progress_an_incident(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);

        $this->expectException(HttpException::class);

        $this->incidents->transition($event, $incident, IncidentStatus::Acknowledged, $general->user);
    }

    public function test_assigned_staff_without_resolve_permission_cannot_resolve(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $supervisor = $this->staffOf($org, $event, EventStaffRole::StationSupervisor);

        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);
        $this->incidents->assign($event, $incident, $supervisor->user, $owner);

        $this->expectException(HttpException::class);

        $this->incidents->transition($event, $incident->fresh(), IncidentStatus::Resolved, $supervisor->user);
    }

    public function test_resolution_requires_notes(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);

        $this->expectException(ValidationException::class);

        $this->incidents->resolve($event, $incident, '   ', $owner);
    }

    public function test_incident_must_be_resolved_before_it_can_be_closed(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);

        $this->expectException(ValidationException::class);

        $this->incidents->close($event, $incident, $owner);
    }

    public function test_serious_incident_requires_resolution_notes_before_closure(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $incident = EventIncident::create([
            'event_id' => $event->id,
            'title' => 'Kebakaran kecil',
            'category' => IncidentCategory::Venue->value,
            'severity' => IncidentSeverity::Critical->value,
            'status' => IncidentStatus::Resolved->value,
            'reported_at' => now(),
            'resolved_at' => now(),
            'resolution_notes' => null,
        ]);

        $this->expectException(ValidationException::class);

        $this->incidents->close($event, $incident, $owner);
    }

    public function test_resolving_and_closing_records_timestamps_and_history(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);

        $resolved = $this->incidents->resolve($event, $incident, 'Sudah diganti', $owner);
        $this->assertSame(IncidentStatus::Resolved, $resolved->status);
        $this->assertNotNull($resolved->resolved_at);

        $closed = $this->incidents->close($event, $resolved, $owner);
        $this->assertSame(IncidentStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertGreaterThanOrEqual(3, $closed->updates()->count());
    }

    public function test_reopening_clears_resolution_and_closure_timestamps(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $owner);

        $resolved = $this->incidents->resolve($event, $incident, 'Selesai', $owner);
        $closed = $this->incidents->close($event, $resolved, $owner);

        $reopened = $this->incidents->reopen($event, $closed, 'Masalah muncul lagi', $owner);

        $this->assertSame(IncidentStatus::InProgress, $reopened->status);
        $this->assertNull($reopened->resolved_at);
        $this->assertNull($reopened->closed_at);
    }

    public function test_reporter_can_escalate_without_management_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $incident = $this->incidents->report($event, ['title' => 'Insiden'], $general->user);

        $escalated = $this->incidents->escalate($event, $incident, null, 'Butuh koordinator', $general->user);

        $this->assertNotNull($escalated->escalated_at);
        $this->assertSame('Butuh koordinator', $escalated->escalation_reason);
    }

    public function test_escalation_cannot_downgrade_a_serious_incident(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $incident = $this->incidents->report($event, [
            'title' => 'Insiden',
            'severity' => IncidentSeverity::Critical->value,
        ], $owner);

        $this->expectException(ValidationException::class);

        $this->incidents->escalate($event, $incident, null, 'Salah input', $owner, IncidentSeverity::Low);
    }

    public function test_incident_from_another_event_is_not_found(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->incidents->report($other, ['title' => 'Lain'], $owner);

        $this->expectException(HttpException::class);

        $this->incidents->resolve($event, $foreign, 'Catatan', $owner);
    }
}
