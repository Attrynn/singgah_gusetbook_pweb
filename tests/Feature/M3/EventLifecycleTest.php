<?php

namespace Tests\Feature\M3;

use App\Enums\EventStaffRole;
use App\Enums\EventStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\EventStaffMembership;
use App\Models\Guest;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = $this->organization('org-lifecycle');
    }

    private function organization(string $slug): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    private function memberOf(Organization $org, OrganizationRole $role): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => OrganizationMembershipStatus::Active->value,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    private function event(User $owner, array $attributes = []): Event
    {
        $event = Event::factory()->create([
            'organization_id' => $this->org->id,
            'status' => EventStatus::Draft->value,
            'event_date' => now()->addDays(10)->toDateString(),
            ...$attributes,
        ]);

        EventOwnerMembership::create([
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'role_label' => 'groom',
            'status' => 'active',
        ]);

        return $event;
    }

    private function statusRoute(Organization $org, Event $event): string
    {
        return route('workspace.events.status', [$org, $event]);
    }

    private function archiveRoute(Organization $org, Event $event): string
    {
        return route('workspace.events.archive', [$org, $event]);
    }

    private function unarchiveRoute(Organization $org, Event $event): string
    {
        return route('workspace.events.unarchive', [$org, $event]);
    }

    private function setupSaveRoute(Organization $org, Event $event): string
    {
        return route('workspace.events.setup.save', [$org, $event]);
    }

    private function teamStoreRoute(Organization $org, Event $event): string
    {
        return route('workspace.events.team.store', [$org, $event]);
    }

    // -------------------------------------------------------------------------
    // Valid transitions
    // -------------------------------------------------------------------------

    public function test_draft_to_published_transition_succeeds(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'published'])
            ->assertRedirect();

        $event->refresh();

        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertNotNull($event->published_at);
    }

    public function test_published_to_live_transition_succeeds(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Published->value]);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'live'])
            ->assertRedirect();

        $this->assertSame(EventStatus::Live, $event->refresh()->status);
    }

    public function test_live_to_completed_transition_succeeds(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Live->value]);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame(EventStatus::Completed, $event->refresh()->status);
    }

    public function test_completed_to_archived_transition_succeeds(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Completed->value]);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'archived'])
            ->assertRedirect();

        $event->refresh();

        $this->assertSame(EventStatus::Archived, $event->status);
        $this->assertNotNull($event->archived_at);
    }

    // -------------------------------------------------------------------------
    // Invalid transitions
    // -------------------------------------------------------------------------

    public function test_invalid_transition_is_rejected(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'completed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(EventStatus::Draft, $event->refresh()->status);
    }

    public function test_draft_to_live_transition_is_rejected(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'live'])
            ->assertSessionHasErrors('status');

        $this->assertSame(EventStatus::Draft, $event->refresh()->status);
    }

    // -------------------------------------------------------------------------
    // Creation defaults
    // -------------------------------------------------------------------------

    public function test_creating_event_defaults_to_draft_and_is_not_auto_published(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);

        $this->actingAs($owner)->post(route('workspace.events.store', $this->org), [
            'name' => 'New Wedding',
            'groom_name' => 'Groom',
            'bride_name' => 'Bride',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue_name' => 'Venue',
            'venue_address' => 'Jalan',
            'timezone' => 'Asia/Jakarta',
        ])->assertRedirect();

        $created = Event::where('name', 'New Wedding')->first();

        $this->assertNotNull($created);
        $this->assertSame(EventStatus::Draft, $created->status);
        $this->assertNull($created->published_at);
        $this->assertSame($this->org->id, $created->organization_id);
    }

    // -------------------------------------------------------------------------
    // Archive permission
    // -------------------------------------------------------------------------

    public function test_archive_requires_permission(): void
    {
        $member = $this->memberOf($this->org, OrganizationRole::Member);
        $event = $this->event($this->memberOf($this->org, OrganizationRole::Admin));

        $this->actingAs($member)->post($this->archiveRoute($this->org, $event))
            ->assertForbidden();
    }

    public function test_event_staff_cannot_archive(): void
    {
        $admin = $this->memberOf($this->org, OrganizationRole::Admin);
        $event = $this->event($admin);

        $staff = $this->memberOf($this->org, OrganizationRole::Member);

        EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $staff->id,
            'role' => EventStaffRole::EventManager,
            'status' => 'active',
        ]);

        $this->actingAs($staff)->post($this->archiveRoute($this->org, $event))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Archiving preserves related data
    // -------------------------------------------------------------------------

    public function test_archiving_preserves_related_data(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner);

        Guest::factory()->count(3)->create(['event_id' => $event->id]);
        $partner = $this->memberOf($this->org, OrganizationRole::Member);

        EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $owner->id,
            'role' => EventStaffRole::EventManager,
            'status' => 'active',
        ]);
        EventOwnerMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $partner->id,
            'role_label' => 'bride',
            'status' => 'active',
        ]);

        $beforeGuests = Guest::where('event_id', $event->id)->count();
        $beforeStaff = EventStaffMembership::where('event_id', $event->id)->count();
        $beforeOwners = EventOwnerMembership::where('event_id', $event->id)->count();

        $this->actingAs($owner)->post($this->archiveRoute($this->org, $event))
            ->assertRedirect();

        $this->assertSame($beforeGuests, Guest::where('event_id', $event->id)->count());
        $this->assertSame($beforeStaff, EventStaffMembership::where('event_id', $event->id)->count());
        $this->assertSame($beforeOwners, EventOwnerMembership::where('event_id', $event->id)->count());
    }

    // -------------------------------------------------------------------------
    // Unarchive
    // -------------------------------------------------------------------------

    public function test_unarchive_works_for_authorized_user(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Archived->value]);

        $this->actingAs($owner)->post($this->unarchiveRoute($this->org, $event))
            ->assertRedirect();

        $this->assertSame(EventStatus::Published, $event->refresh()->status);
    }

    public function test_unarchive_blocked_for_unauthorized_user(): void
    {
        $member = $this->memberOf($this->org, OrganizationRole::Member);
        $event = $this->event(
            $this->memberOf($this->org, OrganizationRole::Admin),
            ['status' => EventStatus::Archived->value]
        );

        $this->actingAs($member)->post($this->unarchiveRoute($this->org, $event))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Archived events reject operational mutations
    // -------------------------------------------------------------------------

    public function test_archived_event_rejects_setup_save(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Archived->value]);

        $this->actingAs($owner)->put($this->setupSaveRoute($this->org, $event), [])
            ->assertForbidden();
    }

    public function test_archived_event_rejects_team_store(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Archived->value]);
        $candidate = $this->memberOf($this->org, OrganizationRole::Member);

        $this->actingAs($owner)->post($this->teamStoreRoute($this->org, $event), [
            'user_id' => $candidate->id,
            'role' => EventStaffRole::GuestManager->value,
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Audit rows
    // -------------------------------------------------------------------------

    public function test_status_transition_records_audit_row(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Published->value]);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'live'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.status_changed',
            'event_id' => $event->id,
        ]);
    }

    public function test_archive_records_audit_row(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Completed->value]);

        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'archived'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'event.status_changed',
            'event_id' => $event->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Concurrent / double transition safety
    // -------------------------------------------------------------------------

    public function test_double_transition_is_handled_safely(): void
    {
        $owner = $this->memberOf($this->org, OrganizationRole::Owner);
        $event = $this->event($owner, ['status' => EventStatus::Published->value]);

        // published -> live (valid)
        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'live'])
            ->assertRedirect();

        // live -> live (idempotent, must not error or duplicate the audit row)
        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'live'])
            ->assertRedirect();

        // live -> draft (invalid)
        $this->actingAs($owner)->post($this->statusRoute($this->org, $event), ['status' => 'draft'])
            ->assertSessionHasErrors('status');

        $this->assertSame(EventStatus::Live, $event->refresh()->status);

        $this->assertSame(1, AuditLog::where('event_id', $event->id)
            ->where('action', 'event.status_changed')
            ->count());
    }

    public function test_event_manager_cannot_unarchive_an_archived_event_via_status(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'status' => EventStatus::Archived->value,
        ]);
        $manager = $this->memberOf($org, OrganizationRole::Member);
        EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $manager->id,
            'role' => EventStaffRole::EventManager->value,
            'status' => 'active',
        ]);

        $this->actingAs($manager)
            ->post(route('workspace.events.status', [$org, $event]), ['status' => 'published'])
            ->assertForbidden();

        $this->assertSame(EventStatus::Archived, $event->fresh()->status);
    }

    public function test_archived_event_rejects_update_via_put(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create([
            'organization_id' => $org->id,
            'status' => EventStatus::Archived->value,
            'name' => 'Original Name',
        ]);
        $owner = $this->memberOf($org, OrganizationRole::Owner);

        $this->actingAs($owner)
            ->put(route('workspace.events.update', [$org, $event]), [
                'name' => 'Attempted Edit',
                'event_date' => $event->event_date->format('Y-m-d'),
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'timezone' => $event->timezone,
            ])
            ->assertForbidden();

        $this->assertSame('Original Name', $event->fresh()->name);
    }
}
