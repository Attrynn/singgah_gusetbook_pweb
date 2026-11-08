<?php

namespace Tests\Feature\M2;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventStaffMembershipTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->permissions = app(PermissionService::class);
    }

    private function assign(User $user, Event $event, EventStaffRole $role): EventStaffMembership
    {
        return EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => $role->value,
        ]);
    }

    public function test_staff_permission_applies_only_to_assigned_event(): void
    {
        $org = Organization::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $org->id]);
        $event2 = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event1, EventStaffRole::GuestManager);

        $this->assertTrue($this->permissions->hasEventPermission($user, $event1, PermissionRegistry::GUEST_VIEW));
        $this->assertFalse($this->permissions->hasEventPermission($user, $event2, PermissionRegistry::GUEST_VIEW));
    }

    public function test_staff_of_org_a_has_no_permission_on_event_in_org_b(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $eventA = Event::factory()->create(['organization_id' => $orgA->id]);
        $eventB = Event::factory()->create(['organization_id' => $orgB->id]);
        $user = User::factory()->create();
        $this->assign($user, $eventA, EventStaffRole::GuestManager);

        $this->assertNotSame($eventA->id, $eventB->id);
        $this->assertFalse($this->permissions->hasEventPermission($user, $eventB, PermissionRegistry::GUEST_VIEW));
    }

    public function test_staff_assigned_to_event_one_has_no_permission_on_event_two(): void
    {
        $org = Organization::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $org->id]);
        $event2 = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event1, EventStaffRole::GuestManager);

        $this->assertFalse($this->permissions->hasEventPermission($user, $event2, PermissionRegistry::GUEST_CREATE));
        $this->assertFalse($this->permissions->hasEventPermission($user, $event2, PermissionRegistry::GUEST_UPDATE));
    }

    public function test_multiple_staff_roles_union_permissions(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event, EventStaffRole::GuestManager);
        $this->assign($user, $event, EventStaffRole::CheckinSupervisor);

        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_UPDATE));
        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::CHECKIN_CORRECT));
    }

    public function test_revoked_role_loses_access_immediately(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $staff = $this->assign($user, $event, EventStaffRole::GuestManager);

        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_VIEW));

        $staff->update(['status' => 'revoked']);

        $this->assertFalse($this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_VIEW));
    }

    public function test_checkin_staff_does_not_receive_guest_manager_or_export_rights(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event, EventStaffRole::CheckinStaff);

        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::CHECKIN_SCAN));
        $this->assertFalse($this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_UPDATE));
        $this->assertFalse($this->permissions->hasEventPermission($user, $event, PermissionRegistry::REPORTS_EXPORT));
    }

    public function test_media_curator_does_not_gain_workspace_owner_rights(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event, EventStaffRole::MediaCurator);

        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::MEDIA_MODERATE));
        $this->assertFalse($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_MANAGE_MEMBERS));
    }

    public function test_gift_steward_gains_only_gift_permissions(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event, EventStaffRole::GiftSteward);

        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::GIFT_VIEW));
        $this->assertTrue($this->permissions->hasEventPermission($user, $event, PermissionRegistry::GIFT_MANAGE));
        $this->assertFalse($this->permissions->hasOrgPermission($user, $org, PermissionRegistry::ORG_MANAGE_MEMBERS));
        $this->assertFalse($this->permissions->hasEventPermission($user, $event, PermissionRegistry::MEDIA_MODERATE));
    }

    public function test_duplicate_event_user_role_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $user = User::factory()->create();
        $this->assign($user, $event, EventStaffRole::GuestManager);

        $this->expectException(QueryException::class);

        EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::GuestManager->value,
        ]);
    }
}
