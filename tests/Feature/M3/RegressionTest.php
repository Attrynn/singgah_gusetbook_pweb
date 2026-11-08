<?php

namespace Tests\Feature\M3;

use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

/**
 * Lightweight M3 regression guard. The broader suite covers the rest; this
 * file only pins the handful of existing behaviours most likely to be affected
 * by the milestone-3 workspace routing, duplicate and tenant-scoping changes.
 */
class RegressionTest extends TestCase
{
    use BuildsWedding;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

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

    public function test_admin_events_remains_platform_superadmin_only(): void
    {
        $owner = User::factory()->owner()->create(['is_active' => true]);

        $this->actingAs($owner)->get(route('admin.events.index'))->assertForbidden();

        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->get(route('admin.events.index'))->assertOk();
    }

    public function test_legacy_event_still_loads_owner_dashboard_and_public_page(): void
    {
        $owner = User::factory()->owner()->create(['is_active' => true]);
        $event = Event::factory()->create(['organization_id' => null]);
        $owner->events()->attach($event->id, ['role_label' => 'groom']);

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->get(route('guest.landing', $event->slug))->assertOk();
    }

    public function test_existing_guest_invitation_token_still_resolves(): void
    {
        $event = $this->makeEvent(['groom_name' => 'Raka', 'bride_name' => 'Alya']);
        $guest = $this->makeGuest($event, ['name' => 'Tamu Istimewa']);
        $token = $this->invitationToken($guest);

        $this->get(route('guest.invitation', $token))
            ->assertOk()
            ->assertSee('Tamu Istimewa');
    }

    public function test_workspace_members_route_still_works_for_org_owner(): void
    {
        $org = $this->organization('regress-org');
        $owner = $this->memberOf($org, 'owner');

        $this->actingAs($owner)->get(route('workspace.members.index', $org))->assertOk();
    }
}
