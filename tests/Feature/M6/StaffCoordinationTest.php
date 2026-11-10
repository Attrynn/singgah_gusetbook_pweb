<?php

namespace Tests\Feature\M6;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\EventRunOfShowAssignee;
use App\Models\EventRunOfShowItem;
use App\Models\EventStation;
use App\Models\EventStationAssignment;
use App\Models\EventTask;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\StaffOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M6\Concerns\BuildsOperationsFixtures;
use Tests\TestCase;

class StaffCoordinationTest extends TestCase
{
    use BuildsOperationsFixtures, RefreshDatabase;

    private StaffOperationsService $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = app(StaffOperationsService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_inviting_a_user_outside_the_workspace_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $outsider = User::factory()->create(['is_active' => true]);

        $this->expectException(ValidationException::class);

        $this->staff->invite($event, $outsider, EventStaffRole::GeneralStaff, $owner);
    }

    public function test_invite_creates_a_pending_membership_with_invitation_metadata(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $target = $this->memberOf($org);

        $membership = $this->staff->invite($event, $target, EventStaffRole::GeneralStaff, $owner);

        $this->assertSame(MembershipStatus::Pending, $membership->status);
        $this->assertSame($owner->id, $membership->invited_by);
        $this->assertNotNull($membership->invited_at);
        $this->assertNull($membership->accepted_at);
    }

    public function test_inviting_an_already_active_role_is_rejected(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->expectException(ValidationException::class);

        $this->staff->invite($event, $membership->user, EventStaffRole::GeneralStaff, $owner);
    }

    public function test_only_the_invitee_can_accept_an_invitation(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $target = $this->memberOf($org);
        $membership = $this->staff->invite($event, $target, EventStaffRole::GeneralStaff, $owner);

        $this->expectException(HttpException::class);

        $this->staff->accept($event, $membership, $owner);
    }

    public function test_invitee_accepts_and_becomes_active(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $target = $this->memberOf($org);
        $membership = $this->staff->invite($event, $target, EventStaffRole::GeneralStaff, $owner);

        $accepted = $this->staff->accept($event, $membership, $target);

        $this->assertSame(MembershipStatus::Active, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);
    }

    public function test_invitee_can_decline(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $target = $this->memberOf($org);
        $membership = $this->staff->invite($event, $target, EventStaffRole::GeneralStaff, $owner);

        $declined = $this->staff->decline($event, $membership, $target);

        $this->assertSame(MembershipStatus::Declined, $declined->status);
        $this->assertNotNull($declined->declined_at);
    }

    public function test_suspending_a_staff_member_releases_all_operational_responsibilities(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::StationSupervisor);

        $station = EventStation::create([
            'event_id' => $event->id,
            'name' => 'Registrasi',
            'slug' => 'registrasi',
            'category' => 'registration',
            'status' => 'ready',
            'supervisor_membership_id' => $membership->id,
        ]);

        EventStationAssignment::create([
            'event_id' => $event->id,
            'station_id' => $station->id,
            'staff_membership_id' => $membership->id,
            'is_supervisor' => true,
            'assigned_at' => now(),
        ]);

        $item = EventRunOfShowItem::create([
            'event_id' => $event->id,
            'title' => 'Penyambutan tamu',
            'status' => 'pending',
            'sequence' => 1,
            'pic_staff_membership_id' => $membership->id,
        ]);

        EventRunOfShowAssignee::create([
            'event_id' => $event->id,
            'run_of_show_item_id' => $item->id,
            'staff_membership_id' => $membership->id,
        ]);

        $task = EventTask::create([
            'event_id' => $event->id,
            'title' => 'Siapkan meja registrasi',
            'priority' => 'high',
            'status' => 'assigned',
            'assigned_membership_id' => $membership->id,
        ]);

        $suspended = $this->staff->suspend($event, $membership, $owner, 'Pelanggaran prosedur');

        $this->assertSame(MembershipStatus::Suspended, $suspended->status);
        $this->assertNotNull(
            EventStationAssignment::query()
                ->where('station_id', $station->id)
                ->where('staff_membership_id', $membership->id)
                ->first()
                ?->removed_at,
        );
        $this->assertNull($station->fresh()->supervisor_membership_id);
        $this->assertDatabaseMissing('event_run_of_show_assignees', ['staff_membership_id' => $membership->id]);
        $this->assertNull($item->fresh()->pic_staff_membership_id);

        $task->refresh();
        $this->assertNull($task->assigned_membership_id);
        $this->assertSame('blocked', $task->status->value);
        $this->assertStringContainsString('Pelanggaran prosedur', (string) $task->blocked_reason);
    }

    public function test_a_staff_member_cannot_suspend_their_own_membership(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::OperationsCoordinator);

        $this->expectException(ValidationException::class);

        $this->staff->suspend($event, $membership, $membership->user);
    }

    public function test_only_active_staff_can_be_suspended(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::GeneralStaff, MembershipStatus::Pending);

        $this->expectException(ValidationException::class);

        $this->staff->suspend($event, $membership, $owner);
    }

    public function test_suspended_staff_can_be_reinstated(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->staff->suspend($event, $membership, $owner, 'Sementara');
        $restored = $this->staff->reinstate($event, $membership, $owner);

        $this->assertSame(MembershipStatus::Active, $restored->status);
        $this->assertNull($restored->suspended_at);
    }

    public function test_removing_staff_revokes_membership_and_deletes_permission_grants(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->staff->grantPermissions($event, $membership, [PermissionRegistry::TASK_MANAGE], $owner);

        $removed = $this->staff->remove($event, $membership, $owner);

        $this->assertSame(MembershipStatus::Removed, $removed->status);
        $this->assertNotNull($removed->removed_at);
        $this->assertDatabaseCount('event_staff_permission_grants', 0);
    }

    public function test_permissions_cannot_be_delegated_beyond_the_actor_authority(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->expectException(ValidationException::class);

        $this->staff->grantPermissions($event, $membership, [PermissionRegistry::EVENT_DELETE], $owner);
    }

    public function test_coordinator_cannot_manage_permission_delegation(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $coordinator = $this->staffOf($org, $event, EventStaffRole::OperationsCoordinator);
        $target = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->expectException(HttpException::class);

        $this->staff->grantPermissions($event, $target, [PermissionRegistry::TASK_MANAGE], $coordinator->user);
    }

    public function test_delegated_permission_is_effective_for_the_staff_member(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $owner = $this->owner($org);
        $membership = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->assertFalse(app(PermissionService::class)->hasEventPermission($membership->user, $event, PermissionRegistry::TASK_MANAGE));

        $this->staff->grantPermissions($event, $membership, [PermissionRegistry::TASK_MANAGE], $owner);

        $this->assertTrue(app(PermissionService::class)->hasEventPermission($membership->user->fresh(), $event, PermissionRegistry::TASK_MANAGE));
    }

    public function test_coordinator_cannot_invite_a_role_with_broader_permissions(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $coordinator = $this->staffOf($org, $event, EventStaffRole::OperationsCoordinator);
        $target = $this->memberOf($org);

        $this->expectException(ValidationException::class);

        $this->staff->invite($event, $target, EventStaffRole::EventManager, $coordinator->user);
    }

    public function test_membership_from_another_event_is_not_found(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $other = $this->eventIn($org);
        $owner = $this->owner($org);
        $foreign = $this->staffOf($org, $other, EventStaffRole::GeneralStaff);

        $this->expectException(HttpException::class);

        $this->staff->suspend($event, $foreign, $owner);
    }
}
