<?php

namespace Tests\Feature\M3;

use App\Authorization\PermissionRegistry;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventRunOfShowItem;
use App\Models\EventSchedule;
use App\Models\EventSession;
use App\Models\EventSetupConfig;
use App\Models\EventStaffMembership;
use App\Models\EventVenue;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Playbook;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class SecurityTenantIsolationTest extends TestCase
{
    use BuildsWedding;
    use RefreshDatabase;

    protected function organization(string $slug): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    protected function memberOf(Organization $org, string $role): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        return $user;
    }

    /**
     * An actor who belongs to orgA must be denied every resource that lives in
     * orgB when probed through orgA's workspace path.
     */
    public function test_actor_in_org_a_is_denied_org_b_portfolio(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');

        $this->actingAs($actor)->get(route('workspace.events.index', $orgB))->assertForbidden();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_event_show(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($actor)->get(route('workspace.events.show', [$orgA, $eventB]))->assertNotFound();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_event_team(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($actor)->get(route('workspace.events.team.index', [$orgA, $eventB]))->assertNotFound();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_event_schedule(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($actor)->get(route('workspace.events.schedule.index', [$orgA, $eventB]))->assertNotFound();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_event_run_of_show(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($actor)->get(route('workspace.events.run-of-show.index', [$orgA, $eventB]))->assertNotFound();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_event_duplicate(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($actor)
            ->post(route('workspace.events.duplicate', [$orgA, $eventB]))
            ->assertNotFound();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_playbook_show(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $playbookB = Playbook::factory()->forOrganization($orgB)->create();

        $this->actingAs($actor)->get(route('workspace.playbooks.show', [$orgA, $playbookB]))->assertNotFound();
    }

    public function test_actor_in_org_a_is_forbidden_from_b_playbook_update(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'admin');
        $playbookB = Playbook::factory()->forOrganization($orgB)->create();

        $this->actingAs($actor)
            ->put(route('workspace.playbooks.update', [$orgA, $playbookB]), ['name' => 'tampered'])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Cross-tenant leakage
    // -------------------------------------------------------------------------

    public function test_portfolio_query_never_returns_other_org_events(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');

        Event::factory()->create([
            'organization_id' => $orgA->id,
            'name' => 'Acara Alpha',
            'groom_name' => 'Alpha',
            'bride_name' => 'Satu',
        ]);
        Event::factory()->create([
            'organization_id' => $orgB->id,
            'name' => 'Acara Bravo',
            'groom_name' => 'Bravo',
            'bride_name' => 'Dua',
        ]);

        $response = $this->actingAs($actor)->get(route('workspace.events.index', $orgA));

        $response->assertOk();
        $response->assertSee('Acara Alpha');
        $response->assertDontSee('Acara Bravo');
    }

    public function test_team_roster_never_includes_other_org_users(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $orgBUser = $this->memberOf($orgB, 'member');

        $eventA = Event::factory()->create(['organization_id' => $orgA->id]);

        $response = $this->actingAs($actor)->get(route('workspace.events.team.index', [$orgA, $eventA]));

        $response->assertOk();
        $response->assertDontSee($orgBUser->name);
    }

    public function test_schedule_and_run_of_show_queries_never_return_other_events(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'owner');

        $eventA = Event::factory()->create(['organization_id' => $orgA->id]);
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);

        EventSchedule::create([
            'event_id' => $eventA->id,
            'title' => 'Jadwal A',
            'description' => null,
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
            'status' => 'planned',
            'sort_order' => 0,
        ]);
        EventSchedule::create([
            'event_id' => $eventB->id,
            'title' => 'Jadwal B',
            'description' => null,
            'start_time' => now()->addDay()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
            'status' => 'planned',
            'sort_order' => 0,
        ]);

        EventRunOfShowItem::create([
            'event_id' => $eventA->id,
            'title' => 'RoS A',
            'status' => 'pending',
            'sequence' => 0,
        ]);
        EventRunOfShowItem::create([
            'event_id' => $eventB->id,
            'title' => 'RoS B',
            'status' => 'pending',
            'sequence' => 0,
        ]);

        $response = $this->actingAs($actor)->get(route('workspace.events.schedule.index', [$orgA, $eventA]));

        $response->assertOk();
        $response->assertSee('Jadwal A');
        $response->assertDontSee('Jadwal B');

        $response = $this->actingAs($actor)->get(route('workspace.events.run-of-show.index', [$orgA, $eventA]));

        $response->assertOk();
        $response->assertSee('RoS A');
        $response->assertDontSee('RoS B');
    }

    // -------------------------------------------------------------------------
    // Public / internal separation
    // -------------------------------------------------------------------------

    public function test_guest_page_never_exposes_internal_run_of_show_data(): void
    {
        $org = $this->organization('iso-pub');
        $event = $this->makeEvent(['organization_id' => $org->id]);

        EventRunOfShowItem::create([
            'event_id' => $event->id,
            'phase_id' => null,
            'title' => 'Akad',
            'internal_description' => 'SECRET_INTERNAL_PIC_NAME@staff.example',
            'planned_start' => now()->addDay()->toDateTimeString(),
            'planned_end' => now()->addDay()->toDateTimeString(),
            'actual_start' => null,
            'actual_end' => null,
            'status' => 'pending',
            'sequence' => 0,
            'pic_staff_membership_id' => null,
            'internal_notes' => 'internal note secret',
        ]);

        $response = $this->get(route('guest.landing', $event->slug));

        $response->assertOk();
        $response->assertDontSee('SECRET_INTERNAL_PIC_NAME@staff.example');
        $response->assertDontSee('internal note secret');
    }

    public function test_guest_page_never_exposes_staff_email(): void
    {
        $org = $this->organization('iso-pub');
        $event = $this->makeEvent(['organization_id' => $org->id]);

        $response = $this->get(route('guest.landing', $event->slug));

        $response->assertOk();
        $response->assertDontSee('manager@staff.example');
    }

    // -------------------------------------------------------------------------
    // Assignment security
    // -------------------------------------------------------------------------

    public function test_cannot_assign_user_from_another_org(): void
    {
        $orgA = $this->organization('iso-a');
        $orgB = $this->organization('iso-b');
        $actor = $this->memberOf($orgA, 'admin');
        $target = $this->memberOf($orgB, 'member');

        $event = Event::factory()->create(['organization_id' => $orgA->id]);

        $this->actingAs($actor)
            ->post(route('workspace.events.team.store', [$orgA, $event]), [
                'user_id' => $target->id,
                'role' => 'guest_manager',
            ])
            ->assertStatus(422);
    }

    public function test_admin_cannot_self_escalate_role_beyond_authority(): void
    {
        $orgA = $this->organization('iso-a');
        $admin = $this->memberOf($orgA, 'admin');
        $member = $this->memberOf($orgA, 'member');

        $this->actingAs($admin)
            ->put(route('workspace.members.update', [$orgA, $member]), [
                'role' => OrganizationRole::Owner->value,
            ])
            ->assertForbidden();
    }

    public function test_cannot_set_primary_pic_with_arbitrary_membership(): void
    {
        $orgA = $this->organization('iso-a');
        $actor = $this->memberOf($orgA, 'admin');

        $eventA = Event::factory()->create(['organization_id' => $orgA->id]);
        $eventB = Event::factory()->create(['organization_id' => $orgA->id]);

        $membershipA = EventStaffMembership::create([
            'event_id' => $eventA->id,
            'user_id' => $actor->id,
            'role' => 'event_manager',
            'status' => 'active',
        ]);
        EventStaffMembership::create([
            'event_id' => $eventB->id,
            'user_id' => $actor->id,
            'role' => 'event_manager',
            'status' => 'active',
        ]);

        // Revoked membership still passes the existence validation but is rejected
        // by setPrimaryPic, which requires an active membership on the same event.
        $membershipA->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->actingAs($actor)
            ->post(route('workspace.events.team.primary', [$orgA, $eventA]), [
                'membership_id' => $membershipA->id,
            ])
            ->assertStatus(422);
    }

    public function test_revoking_removes_access_to_event(): void
    {
        $orgA = $this->organization('iso-a');
        $owner = $this->memberOf($orgA, 'owner');
        $member = $this->memberOf($orgA, 'member');

        $event = Event::factory()->create(['organization_id' => $orgA->id]);

        $membership = EventStaffMembership::create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'role' => 'checkin_staff',
            'status' => 'active',
        ]);

        $permissions = app(PermissionService::class);

        $this->assertTrue(
            $permissions->hasEventPermission($member, $event, PermissionRegistry::EVENT_RUN_OF_SHOW_VIEW),
            'Active event staff must hold event permissions.'
        );

        $this->actingAs($owner)
            ->delete(route('workspace.events.team.destroy', [$orgA, $event, $membership]))
            ->assertRedirect();

        $this->assertFalse(
            $permissions->hasEventPermission($member, $event, PermissionRegistry::EVENT_RUN_OF_SHOW_VIEW),
            'Revoked event staff must lose event permissions.'
        );
    }

    // -------------------------------------------------------------------------
    // Duplicate isolation
    // -------------------------------------------------------------------------

    public function test_duplicate_target_org_always_equals_source_org(): void
    {
        $orgA = $this->organization('dup-iso');
        $orgB = $this->organization('dup-iso-b');
        $actor = $this->memberOf($orgA, 'owner');
        $source = $this->richSourceEvent($orgA);

        // An attacker tries to redirect the copy into orgB via the request body.
        $body = array_merge(['name' => 'Salinan'], ['organization_id' => $orgB->id]);

        $before = $orgA->events()->pluck('id')->all();

        $this->actingAs($actor)
            ->post(route('workspace.events.duplicate', [$orgA, $source]), $body)
            ->assertRedirect();

        $copy = $orgA->events()->whereNotIn('id', $before)->firstOrFail();

        $this->assertSame((int) $orgA->id, (int) $copy->organization_id, 'The injected organization_id must be ignored.');
        $this->assertNotSame((int) $orgB->id, (int) $copy->organization_id);
    }

    /**
     * Build a fully-populated source event so the duplicate can exercise every
     * allowlisted structural copy path at once.
     */
    protected function richSourceEvent(Organization $org): Event
    {
        $event = $this->makeEvent(['organization_id' => $org->id]);

        $phaseA = EventPhase::create([
            'event_id' => $event->id,
            'name' => 'Akad',
            'sequence' => 0,
        ]);
        $phaseB = EventPhase::create([
            'event_id' => $event->id,
            'name' => 'Resepsi',
            'sequence' => 1,
        ]);

        EventSession::create([
            'event_id' => $event->id,
            'phase_id' => $phaseA->id,
            'name' => 'Akad sore',
            'session_date' => now()->addDays(10)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'timezone' => 'Asia/Jakarta',
            'sequence' => 0,
        ]);

        EventSchedule::create([
            'event_id' => $event->id,
            'title' => 'Jadwal Akad',
            'description' => 'Sore hari',
            'start_time' => now()->addDays(10)->setTime(10, 0)->toDateTimeString(),
            'end_time' => now()->addDays(10)->setTime(12, 0)->toDateTimeString(),
            'status' => 'planned',
            'sort_order' => 0,
        ]);

        EventRunOfShowItem::create([
            'event_id' => $event->id,
            'phase_id' => $phaseA->id,
            'title' => 'Akad',
            'internal_description' => 'Pembacaan janji',
            'planned_start' => now()->addDays(10)->setTime(10, 0)->toDateTimeString(),
            'planned_end' => now()->addDays(10)->setTime(12, 0)->toDateTimeString(),
            'actual_start' => now()->subDay()->setTime(10, 0)->toDateTimeString(),
            'actual_end' => now()->subDay()->setTime(12, 0)->toDateTimeString(),
            'status' => 'completed',
            'sequence' => 0,
            'pic_staff_membership_id' => null,
            'internal_notes' => 'catatan internal',
        ]);

        EventRunOfShowItem::create([
            'event_id' => $event->id,
            'phase_id' => $phaseB->id,
            'title' => 'Resepsi',
            'internal_description' => 'resepsi',
            'planned_start' => now()->addDays(11)->setTime(14, 0)->toDateTimeString(),
            'planned_end' => now()->addDays(11)->setTime(16, 0)->toDateTimeString(),
            'status' => 'pending',
            'sequence' => 1,
            'pic_staff_membership_id' => null,
            'internal_notes' => 'internal notes here',
        ]);

        EventSetupConfig::create([
            'event_id' => $event->id,
            'playbook_id' => null,
            'playbook_version_id' => null,
            'source_event_id' => null,
            'config_snapshot' => [
                'phases' => [
                    ['name' => 'Akad', 'sequence' => 0],
                    ['name' => 'Resepsi', 'sequence' => 1],
                ],
                'checklist' => [
                    ['label' => 'Undangan', 'required' => true, 'sequence' => 0],
                    ['label' => 'Dekorasi', 'required' => false, 'sequence' => 1],
                ],
                'public_schedule' => [
                    ['title' => 'Jadwal Akad', 'description' => 'Sore'],
                ],
                'run_of_show' => [
                    ['title' => 'Akad', 'internal_description' => 'Pembacaan janji'],
                ],
            ],
            'setup_status' => 'in_progress',
            'primary_pic_staff_membership_id' => null,
        ]);

        EventVenue::create([
            'event_id' => $event->id,
            'name' => 'Ballroom Utama',
            'address' => 'Jalan Sudirman 1',
            'google_maps_url' => 'https://maps.google.com/?q=x',
            'latitude' => -6.2088,
            'longitude' => 106.8456,
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        return $event;
    }
}
