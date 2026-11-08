<?php

namespace Tests\Feature\M2;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RouteAuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function membersIndex(Organization $org): string
    {
        return route('workspace.members.index', $org);
    }

    protected function organization(string $slug): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    protected function memberOf(Organization $org, OrganizationRole $role, OrganizationMembershipStatus $status = OrganizationMembershipStatus::Active): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => $status->value,
            'accepted_at' => $status === OrganizationMembershipStatus::Active ? now() : null,
        ]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // Global role gate: /admin
    // -------------------------------------------------------------------------

    public function test_superadmin_can_reach_admin_dashboard(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_non_superadmin_is_forbidden_from_admin_dashboard(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Global role gate: /owner
    // -------------------------------------------------------------------------

    public function test_global_owner_with_event_link_can_reach_owner_dashboard(): void
    {
        $owner = User::factory()->owner()->create();
        $event = Event::factory()->create(['organization_id' => null]);
        $owner->events()->attach($event->id, ['role_label' => 'groom']);

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
    }

    public function test_global_owner_without_event_link_is_forbidden_from_owner_dashboard(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Workspace membership gate: members.index
    // -------------------------------------------------------------------------

    public function test_workspace_owner_can_view_own_members_index(): void
    {
        $orgA = $this->organization('auth-org-a');
        $owner = $this->memberOf($orgA, OrganizationRole::Owner);

        $this->actingAs($owner)->get($this->membersIndex($orgA))->assertOk();
    }

    public function test_workspace_owner_of_a_cannot_view_members_index_of_b(): void
    {
        $orgA = $this->organization('auth-org-a');
        $orgB = $this->organization('auth-org-b');
        $ownerA = $this->memberOf($orgA, OrganizationRole::Owner);

        $this->actingAs($ownerA)->get($this->membersIndex($orgB))->assertForbidden();
    }

    public function test_workspace_admin_can_view_own_members_index(): void
    {
        $orgA = $this->organization('auth-org-a');
        $admin = $this->memberOf($orgA, OrganizationRole::Admin);

        $this->actingAs($admin)->get($this->membersIndex($orgA))->assertOk();
    }

    public function test_workspace_admin_cannot_grant_owner_role(): void
    {
        $orgA = $this->organization('auth-org-a');
        $admin = $this->memberOf($orgA, OrganizationRole::Admin);
        $target = $this->memberOf($orgA, OrganizationRole::Member);

        $this->actingAs($admin)
            ->put(route('workspace.members.update', [$orgA, $target]), ['role' => OrganizationRole::Owner->value])
            ->assertForbidden();
    }

    public function test_active_member_cannot_view_members_index(): void
    {
        $orgA = $this->organization('auth-org-a');
        $member = $this->memberOf($orgA, OrganizationRole::Member);

        $this->actingAs($member)->get($this->membersIndex($orgA))->assertForbidden();
    }

    public function test_suspended_member_cannot_view_members_index(): void
    {
        $orgA = $this->organization('auth-org-a');
        $member = $this->memberOf($orgA, OrganizationRole::Member, OrganizationMembershipStatus::Suspended);

        $this->actingAs($member)->get($this->membersIndex($orgA))->assertForbidden();
    }

    public function test_revoked_member_cannot_view_members_index(): void
    {
        $orgA = $this->organization('auth-org-a');
        $member = $this->memberOf($orgA, OrganizationRole::Member, OrganizationMembershipStatus::Revoked);

        $this->actingAs($member)->get($this->membersIndex($orgA))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login_from_members_index(): void
    {
        $orgA = $this->organization('auth-org-a');

        $this->get($this->membersIndex($orgA))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Invitation show route: bad token must 404, never 500
    // -------------------------------------------------------------------------

    public function test_unauthenticated_visit_to_unknown_invitation_token_is_not_found(): void
    {
        $this->get(route('invitations.workspace.accept', ['token' => 'bogus']))->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // workspace.create resolves
    // -------------------------------------------------------------------------

    public function test_workspace_create_route_resolves_for_authenticated_user(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get(route('workspace.create'))->assertOk();
    }

    // -------------------------------------------------------------------------
    // Invitation creation authority
    // -------------------------------------------------------------------------

    public function test_workspace_owner_can_create_staff_invitation(): void
    {
        $orgA = $this->organization('auth-org-a');
        $owner = $this->memberOf($orgA, OrganizationRole::Owner);

        $this->actingAs($owner)
            ->post(route('workspace.invitations.store', $orgA), [
                'email' => 'invitee@example.com',
                'organization_role' => OrganizationRole::Member->value,
            ])
            ->assertRedirect(route('workspace.members.index', $orgA));
    }

    public function test_active_member_cannot_create_staff_invitation(): void
    {
        $orgA = $this->organization('auth-org-a');
        $member = $this->memberOf($orgA, OrganizationRole::Member);

        $this->actingAs($member)
            ->post(route('workspace.invitations.store', $orgA), [
                'email' => 'invitee@example.com',
                'organization_role' => OrganizationRole::Member->value,
            ])
            ->assertForbidden();
    }
}
