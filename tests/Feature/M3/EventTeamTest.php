<?php

namespace Tests\Feature\M3;

use App\Enums\EventStaffRole;
use App\Enums\EventStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventRunOfShowItem;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventTeamTest extends TestCase
{
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

    protected function memberOf(Organization $org, string $role = 'member'): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMembershipStatus::Active->value,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    protected function eventIn(Organization $org, array $attributes = []): Event
    {
        return Event::factory()->create(array_merge([
            'organization_id' => $org->id,
        ], $attributes));
    }

    // -------------------------------------------------------------------------
    // Assignment
    // -------------------------------------------------------------------------

    public function test_active_org_member_can_be_assigned_as_event_manager(): void
    {
        $org = $this->organization('team-assign');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::EventManager->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('event_staff_memberships', [
            'event_id' => $event->id,
            'user_id' => $target->id,
            'role' => EventStaffRole::EventManager->value,
            'status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_user_from_another_org_is_rejected(): void
    {
        $orgA = $this->organization('team-org-a');
        $orgB = $this->organization('team-org-b');
        $owner = $this->memberOf($orgA, OrganizationRole::Owner->value);
        $foreign = $this->memberOf($orgB, OrganizationRole::Member->value);
        $event = $this->eventIn($orgA);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$orgA, $event]), [
                'user_id' => $foreign->id,
                'role' => EventStaffRole::GuestManager->value,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('event_staff_memberships', [
            'event_id' => $event->id,
            'user_id' => $foreign->id,
        ]);
    }

    public function test_revoked_org_member_cannot_be_assigned(): void
    {
        $org = $this->organization('team-revoked');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        OrganizationMembership::query()
            ->where('user_id', $target->id)
            ->update(['status' => OrganizationMembershipStatus::Revoked->value]);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::GuestManager->value,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('event_staff_memberships', [
            'event_id' => $event->id,
            'user_id' => $target->id,
        ]);
    }

    public function test_suspended_org_member_cannot_be_assigned(): void
    {
        $org = $this->organization('team-suspended');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        OrganizationMembership::query()
            ->where('user_id', $target->id)
            ->update(['status' => OrganizationMembershipStatus::Suspended->value]);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::GuestManager->value,
            ])
            ->assertStatus(422);
    }

    public function test_same_user_can_hold_multiple_roles(): void
    {
        $org = $this->organization('team-multi');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::GuestManager->value,
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::CheckinSupervisor->value,
            ])
            ->assertRedirect();

        $this->assertSame(2, EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('user_id', $target->id)
            ->count());
    }

    public function test_duplicate_identical_role_is_not_duplicated(): void
    {
        $org = $this->organization('team-duplicate');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($owner)
                ->post(route('workspace.events.team.store', [$org, $event]), [
                    'user_id' => $target->id,
                    'role' => EventStaffRole::GuestManager->value,
                ])
                ->assertRedirect();
        }

        $this->assertSame(1, EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('user_id', $target->id)
            ->where('role', EventStaffRole::GuestManager->value)
            ->count());
    }

    // -------------------------------------------------------------------------
    // Revocation
    // -------------------------------------------------------------------------

    public function test_revoked_staff_member_loses_event_access(): void
    {
        $org = $this->organization('team-revoke');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $event = $this->eventIn($org);

        $staff = EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'role' => EventStaffRole::GuestManager->value,
            'status' => MembershipStatus::Active->value,
        ]);
        $target = $staff->user;

        $this->assertTrue($target->canAccessEvent($event));

        $this->actingAs($owner)
            ->delete(route('workspace.events.team.destroy', [$org, $event, $staff]))
            ->assertRedirect();

        $this->assertFalse($target->fresh()->canAccessEvent($event));
        $this->assertDatabaseHas('event_staff_memberships', [
            'id' => $staff->id,
            'status' => MembershipStatus::Revoked->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // Primary PIC
    // -------------------------------------------------------------------------

    public function test_setting_primary_pic_rejects_foreign_or_inactive_membership(): void
    {
        $org = $this->organization('team-primary');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $user = $this->memberOf($org, OrganizationRole::Member->value);

        $foreign = EventStaffMembership::factory()->create([
            'event_id' => $eventB->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::EventManager->value,
            'status' => MembershipStatus::Active->value,
        ]);

        // A membership that lives on another event fails the route validation.
        $this->actingAs($owner)
            ->post(route('workspace.events.team.primary', [$org, $eventA]), [
                'membership_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('membership_id');

        // A revoked membership on the same event passes existence but is
        // rejected by EventTeamService, which requires an active membership.
        $revoked = EventStaffMembership::factory()->revoked()->create([
            'event_id' => $eventA->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::GuestManager->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.primary', [$org, $eventA]), [
                'membership_id' => $revoked->id,
            ])
            ->assertStatus(422);

        $this->assertNull($eventA->fresh()->setupConfig?->primary_pic_staff_membership_id);
    }

    public function test_setting_primary_pic_accepts_active_same_event_membership(): void
    {
        $org = $this->organization('team-primary-ok');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $event = $this->eventIn($org);
        $user = $this->memberOf($org, OrganizationRole::Member->value);

        $membership = EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::EventManager->value,
            'status' => MembershipStatus::Active->value,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.primary', [$org, $event]), [
                'membership_id' => $membership->id,
            ])
            ->assertRedirect();

        $this->assertSame(
            $membership->id,
            $event->fresh()->setupConfig->primary_pic_staff_membership_id,
        );
    }

    // -------------------------------------------------------------------------
    // Audit + authorization
    // -------------------------------------------------------------------------

    public function test_team_changes_write_audit_rows(): void
    {
        $org = $this->organization('team-audit');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::EventManager->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'team.assigned',
            'event_id' => $event->id,
        ]);

        $membership = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $this->actingAs($owner)
            ->post(route('workspace.events.team.primary', [$org, $event]), [
                'membership_id' => $membership->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'team.primary_pic',
            'event_id' => $event->id,
        ]);

        $this->actingAs($owner)
            ->delete(route('workspace.events.team.destroy', [$org, $event, $membership]))
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'team.revoked',
            'event_id' => $event->id,
        ]);
    }

    public function test_revoking_member_nulls_primary_pic_and_ros_item_pic(): void
    {
        $org = $this->organization('team-revoke-pic');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $event = $this->eventIn($org);
        $target = $this->memberOf($org, OrganizationRole::Member->value);

        $membership = EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $target->id,
            'role' => EventStaffRole::EventManager->value,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.primary', [$org, $event]), [
                'membership_id' => $membership->id,
            ])
            ->assertRedirect();

        $item = EventRunOfShowItem::factory()->create([
            'event_id' => $event->id,
            'pic_staff_membership_id' => $membership->id,
            'status' => 'pending',
        ]);

        $this->assertSame($membership->id, $event->setupConfig()->first()->primary_pic_staff_membership_id);
        $this->assertSame($membership->id, $item->fresh()->pic_staff_membership_id);

        $this->actingAs($owner)
            ->delete(route('workspace.events.team.destroy', [$org, $event, $membership]))
            ->assertRedirect();

        $this->assertNull($event->setupConfig()->first()->primary_pic_staff_membership_id);
        $this->assertNull($item->fresh()->pic_staff_membership_id);
    }

    public function test_member_without_team_permission_is_forbidden(): void
    {
        $org = $this->organization('team-forbidden');
        $actor = $this->memberOf($org, OrganizationRole::Member->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org);

        $this->actingAs($actor)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::GuestManager->value,
            ])
            ->assertForbidden();
    }

    public function test_archived_event_rejects_team_mutation(): void
    {
        $org = $this->organization('team-archived');
        $owner = $this->memberOf($org, OrganizationRole::Owner->value);
        $target = $this->memberOf($org, OrganizationRole::Member->value);
        $event = $this->eventIn($org, ['status' => EventStatus::Archived]);

        $this->actingAs($owner)
            ->post(route('workspace.events.team.store', [$org, $event]), [
                'user_id' => $target->id,
                'role' => EventStaffRole::GuestManager->value,
            ])
            ->assertForbidden();
    }
}
