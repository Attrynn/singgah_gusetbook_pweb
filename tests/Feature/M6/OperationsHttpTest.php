<?php

namespace Tests\Feature\M6;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OperationsStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventOperation;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class OperationsHttpTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    /**
     * @return array<string, string>
     */
    private function urls(Organization $organization, Event $event): array
    {
        return [
            'index' => route('workspace.events.operations.index', [$organization, $event]),
            'live' => route('workspace.events.operations.live', [$organization, $event]),
            'my-work' => route('workspace.events.operations.my-work', [$organization, $event]),
            'closure' => route('workspace.events.operations.closure', [$organization, $event]),
            'staff' => route('workspace.events.operations.staff.index', [$organization, $event]),
            'stations' => route('workspace.events.operations.stations.index', [$organization, $event]),
            'tasks' => route('workspace.events.operations.tasks.index', [$organization, $event]),
            'incidents' => route('workspace.events.operations.incidents.index', [$organization, $event]),
            'announcements' => route('workspace.events.operations.announcements.index', [$organization, $event]),
            'reports' => route('workspace.events.operations.reports.index', [$organization, $event]),
        ];
    }

    public function test_owner_can_open_every_operations_screen(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        foreach ($this->urls($org, $event) as $name => $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }

    public function test_live_endpoint_returns_json_metrics(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->getJson(route('workspace.events.operations.live', [$org, $event]))
            ->assertOk()
            ->assertJsonStructure([
                'operation' => ['status'],
                'readiness' => ['status', 'items'],
                'staff',
                'stations',
                'run_sheet',
                'tasks',
                'incidents',
                'announcements',
                'attendance',
            ]);
    }

    public function test_operations_screens_require_authentication(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);

        $this->get(route('workspace.events.operations.index', [$org, $event]))
            ->assertRedirect();
    }

    public function test_staff_without_view_permission_is_forbidden(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($general->user)
            ->get(route('workspace.events.operations.staff.index', [$org, $event]))
            ->assertForbidden();
    }

    public function test_staff_with_view_permission_can_open_the_dashboard(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $general = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($general->user)
            ->get(route('workspace.events.operations.index', [$org, $event]))
            ->assertOk();
    }

    public function test_event_from_another_organization_is_not_accessible(): void
    {
        $org = $this->organization('one');
        $other = $this->organization('two');
        $owner = $this->owner($org);
        $foreignEvent = $this->eventIn($other);

        $this->actingAs($owner)
            ->get(route('workspace.events.operations.index', [$org, $foreignEvent]))
            ->assertNotFound();
    }

    public function test_owner_can_transition_operational_status(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.operations.transition', [$org, $event]), [
                'status' => OperationsStatus::Preparing->value,
            ])
            ->assertRedirect();

        $this->assertSame(
            OperationsStatus::Preparing,
            EventOperation::query()->where('event_id', $event->id)->first()?->status,
        );
    }

    public function test_owner_can_create_a_station_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.operations.stations.store', [$org, $event]), [
                'name' => 'Registrasi',
                'category' => 'registration',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_stations', ['event_id' => $event->id, 'name' => 'Registrasi']);
    }

    public function test_owner_can_create_a_task_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.operations.tasks.store', [$org, $event]), [
                'title' => 'Siapkan kursi',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_tasks', ['event_id' => $event->id, 'title' => 'Siapkan kursi']);
    }

    public function test_owner_can_report_an_incident_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.operations.incidents.store', [$org, $event]), [
                'title' => 'Proyektor mati',
                'category' => 'technical',
                'severity' => 'high',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_incidents', ['event_id' => $event->id, 'title' => 'Proyektor mati']);
    }

    public function test_owner_can_create_an_announcement_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.operations.announcements.store', [$org, $event]), [
                'title' => 'Briefing',
                'body' => 'Kumpul di lobi',
                'audience' => 'all_staff',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_announcements', ['event_id' => $event->id, 'title' => 'Briefing']);
    }

    public function test_draft_announcement_can_be_edited_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.operations.announcements.store', [$org, $event]), [
                'title' => 'Briefing awal',
                'body' => 'Kumpul di lobi',
                'audience' => 'all_staff',
                'requires_acknowledgement' => '1',
            ])
            ->assertRedirect();

        $announcement = EventAnnouncement::query()->where('event_id', $event->id)->firstOrFail();

        $this->actingAs($owner)
            ->put(route('workspace.events.operations.announcements.update', [$org, $event, $announcement]), [
                'title' => 'Briefing revisi',
                'body' => 'Kumpul di aula utama',
                'priority' => 'high',
                'requires_acknowledgement' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $announcement->refresh();

        $this->assertSame('Briefing revisi', $announcement->title);
        $this->assertSame('Kumpul di aula utama', $announcement->body);
        $this->assertFalse($announcement->requires_acknowledgement);
    }

    public function test_acknowledgement_requirement_can_be_enabled_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = EventAnnouncement::create([
            'event_id' => $event->id,
            'title' => 'Info',
            'body' => 'Isi',
            'status' => 'draft',
            'audience' => 'all_staff',
            'priority' => 'normal',
            'requires_acknowledgement' => false,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->put(route('workspace.events.operations.announcements.update', [$org, $event, $announcement]), [
                'title' => 'Info',
                'body' => 'Isi',
                'requires_acknowledgement' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($announcement->fresh()->requires_acknowledgement);
    }

    public function test_published_announcement_cannot_be_edited_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $announcement = EventAnnouncement::create([
            'event_id' => $event->id,
            'title' => 'Sudah terbit',
            'body' => 'Isi',
            'status' => 'published',
            'audience' => 'all_staff',
            'priority' => 'normal',
            'requires_acknowledgement' => false,
            'created_by' => $owner->id,
            'published_at' => now(),
        ]);

        $this->actingAs($owner)
            ->put(route('workspace.events.operations.announcements.update', [$org, $event, $announcement]), [
                'title' => 'Diubah',
            ])
            ->assertSessionHasErrors();

        $this->assertSame('Sudah terbit', $announcement->fresh()->title);
    }

    public function test_invitee_can_accept_staff_invitation_over_http(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $invitee = $this->memberOf($org);

        $membership = EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'role' => EventStaffRole::GeneralStaff->value,
            'status' => MembershipStatus::Pending->value,
            'invited_by' => $owner->id,
            'invited_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->post(route('workspace.events.operations.staff.accept', [$org, $event, $membership]))
            ->assertRedirect();

        $this->assertSame(MembershipStatus::Active, $membership->fresh()->status);
    }

    public function test_staff_report_export_streams_csv_with_header_row(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);

        $response = $this->actingAs($owner)->get(
            route('workspace.events.operations.reports.export', [$org, $event, 'type' => 'staff']),
        );

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Nama,Peran,Status', $content);
    }

    public function test_export_requires_export_permission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $observer = $this->staffOf($org, $event, EventStaffRole::Observer);

        $this->actingAs($observer->user)
            ->get(route('workspace.events.operations.reports.export', [$org, $event, 'type' => 'staff']))
            ->assertForbidden();
    }
}
