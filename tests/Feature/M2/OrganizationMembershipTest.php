<?php

namespace Tests\Feature\M2;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\WorkspaceMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationMembershipTest extends TestCase
{
    use RefreshDatabase;

    private function membership(Organization $org, User $user, string $role = 'member', string $status = 'active'): OrganizationMembership
    {
        return OrganizationMembership::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
        ]);
    }

    public function test_workspace_owner_can_open_members_index(): void
    {
        $org = Organization::factory()->create();
        $owner = User::factory()->create();
        $this->membership($org, $owner, OrganizationRole::Owner->value);

        $response = $this->actingAs($owner)->get(route('workspace.members.index', $org));

        $response->assertOk();
    }

    public function test_workspace_owner_of_a_cannot_open_b_members_index(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $ownerA = User::factory()->create();
        $this->membership($orgA, $ownerA, OrganizationRole::Owner->value);

        $response = $this->actingAs($ownerA)->get(route('workspace.members.index', $orgB));

        $response->assertForbidden();
    }

    public function test_workspace_admin_cannot_grant_owner_role(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $this->membership($org, $admin, OrganizationRole::Admin->value);
        $this->membership($org, $target, OrganizationRole::Member->value);

        $response = $this->actingAs($admin)->put(
            route('workspace.members.update', [$org, $target]),
            ['role' => OrganizationRole::Owner->value],
        );

        $response->assertForbidden();
    }

    public function test_basic_active_member_cannot_open_members_index(): void
    {
        $org = Organization::factory()->create();
        $member = User::factory()->create();
        $this->membership($org, $member, OrganizationRole::Member->value);

        $response = $this->actingAs($member)->get(route('workspace.members.index', $org));

        $response->assertForbidden();
    }

    public function test_suspended_owner_membership_is_denied(): void
    {
        $org = Organization::factory()->create();
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $this->membership($org, $owner, OrganizationRole::Owner->value);
        $this->membership($org, $otherOwner, OrganizationRole::Owner->value);

        app(WorkspaceMembershipService::class)->suspend($org, $owner, $otherOwner);

        $response = $this->actingAs($owner)->get(route('workspace.members.index', $org));

        $response->assertForbidden();
    }

    public function test_revoked_owner_membership_is_denied(): void
    {
        $org = Organization::factory()->create();
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $this->membership($org, $owner, OrganizationRole::Owner->value);
        $this->membership($org, $otherOwner, OrganizationRole::Owner->value);

        app(WorkspaceMembershipService::class)->revoke($org, $owner, $otherOwner);

        $response = $this->actingAs($owner)->get(route('workspace.members.index', $org));

        $response->assertForbidden();
    }

    public function test_last_active_owner_cannot_be_removed(): void
    {
        $org = Organization::factory()->create();
        $owner = User::factory()->create();
        $this->membership($org, $owner, OrganizationRole::Owner->value);

        $response = $this->actingAs($owner)->delete(
            route('workspace.members.destroy', [$org, $owner]),
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
    }

    public function test_last_active_owner_cannot_be_demoted(): void
    {
        $org = Organization::factory()->create();
        $owner = User::factory()->create();
        $this->membership($org, $owner, OrganizationRole::Owner->value);

        $response = $this->actingAs($owner)->put(
            route('workspace.members.update', [$org, $owner]),
            ['role' => OrganizationRole::Member->value],
        );

        $response->assertStatus(422);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role' => OrganizationRole::Owner->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
    }

    public function test_transfer_ownership_is_atomic_and_keeps_an_owner(): void
    {
        $org = Organization::factory()->create();
        $originalOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $this->membership($org, $originalOwner, OrganizationRole::Owner->value);
        $this->membership($org, $secondOwner, OrganizationRole::Owner->value);

        $response = $this->actingAs($originalOwner)->put(
            route('workspace.members.update', [$org, $originalOwner]),
            ['role' => OrganizationRole::Member->value],
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $originalOwner->id,
            'role' => OrganizationRole::Member->value,
        ]);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $secondOwner->id,
            'role' => OrganizationRole::Owner->value,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
        $this->assertTrue($org->fresh()->hasOwner());
    }

    public function test_service_lifecycle_transitions_and_audit_trail(): void
    {
        $service = app(WorkspaceMembershipService::class);
        $org = Organization::factory()->create();
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->membership($org, $target, OrganizationRole::Member->value);

        $service->suspend($org, $target, $actor);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $target->id,
            'status' => OrganizationMembershipStatus::Suspended->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.suspended']);

        $service->restore($org, $target, $actor);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $target->id,
            'status' => OrganizationMembershipStatus::Active->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.restored']);

        $service->changeRole($org, $target, OrganizationRole::Admin, $actor);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $target->id,
            'role' => OrganizationRole::Admin->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.role_changed']);

        $service->revoke($org, $target, $actor);
        $this->assertDatabaseHas('organization_memberships', [
            'organization_id' => $org->id,
            'user_id' => $target->id,
            'status' => OrganizationMembershipStatus::Revoked->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.revoked']);
    }

    public function test_status_lifecycle_casts(): void
    {
        $active = OrganizationMembership::factory()->create();
        $this->assertTrue($active->isActive());
        $this->assertFalse($active->isSuspended());
        $this->assertFalse($active->isRevoked());

        $suspended = OrganizationMembership::factory()->suspended()->create();
        $this->assertTrue($suspended->isSuspended());
        $this->assertFalse($suspended->isActive());
        $this->assertFalse($suspended->isRevoked());

        $revoked = OrganizationMembership::factory()->revoked()->create();
        $this->assertTrue($revoked->isRevoked());
        $this->assertFalse($revoked->isActive());
        $this->assertFalse($revoked->isSuspended());
    }
}
