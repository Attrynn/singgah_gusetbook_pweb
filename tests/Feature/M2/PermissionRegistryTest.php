<?php

namespace Tests\Feature\M2;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = app(PermissionService::class);
    }

    public function test_workspace_owner_permission_set_contains_role_and_member_management(): void
    {
        $owner = PermissionRegistry::forWorkspaceOwner();

        $this->assertContains(PermissionRegistry::ORG_MANAGE_ROLES, $owner);
        $this->assertContains(PermissionRegistry::ORG_INVITE_MEMBERS, $owner);
        $this->assertContains(PermissionRegistry::ORG_MANAGE_MEMBERS, $owner);
    }

    public function test_workspace_admin_is_owner_minus_manage_roles(): void
    {
        $admin = PermissionRegistry::forWorkspaceAdmin();
        $owner = PermissionRegistry::forWorkspaceOwner();

        $this->assertSame(
            array_values(array_diff($owner, [PermissionRegistry::ORG_MANAGE_ROLES])),
            $admin,
        );
        $this->assertNotContains(PermissionRegistry::ORG_MANAGE_ROLES, $admin);
    }

    public function test_each_event_staff_role_maps_to_a_non_empty_permission_set(): void
    {
        foreach (EventStaffRole::cases() as $role) {
            $perms = PermissionRegistry::forEventStaffRole($role);

            $this->assertIsArray($perms);
            $this->assertNotEmpty($perms);
        }

        // Check-in Staff: scan + create, but not guest export / reports export.
        $checkin = PermissionRegistry::forEventStaffRole(EventStaffRole::CheckinStaff);
        $this->assertContains(PermissionRegistry::CHECKIN_SCAN, $checkin);
        $this->assertContains(PermissionRegistry::CHECKIN_CREATE, $checkin);
        $this->assertNotContains(PermissionRegistry::GUEST_EXPORT, $checkin);
        $this->assertNotContains(PermissionRegistry::REPORTS_EXPORT, $checkin);

        // Media Curator: moderate media, but not org member management.
        $media = PermissionRegistry::forEventStaffRole(EventStaffRole::MediaCurator);
        $this->assertContains(PermissionRegistry::MEDIA_MODERATE, $media);
        $this->assertNotContains(PermissionRegistry::ORG_MANAGE_MEMBERS, $media);

        // Gift Steward: gift view + manage only.
        $gift = PermissionRegistry::forEventStaffRole(EventStaffRole::GiftSteward);
        $this->assertContains(PermissionRegistry::GIFT_VIEW, $gift);
        $this->assertContains(PermissionRegistry::GIFT_MANAGE, $gift);
    }

    public function test_member_only_org_role_resolves_only_view_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        OrganizationMembership::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Member->value,
        ]);

        $this->assertTrue($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_VIEW));
        $this->assertFalse($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_UPDATE));
        $this->assertFalse($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_MANAGE_MEMBERS));
        $this->assertFalse($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_MANAGE_ROLES));
        $this->assertFalse($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_INVITE_MEMBERS));
    }
}
