<?php

namespace Tests\Feature\M2;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventOwnerMembership;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class GlobalIdentityTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_one_user_holds_many_organization_memberships(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        OrganizationMembership::factory()->owner()->create([
            'organization_id' => $orgA->id,
            'user_id' => $user->id,
        ]);
        OrganizationMembership::factory()->create([
            'organization_id' => $orgB->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(2, $user->organizationMemberships()->count());

        $membershipA = $user->organizationMembershipFor($orgA);
        $membershipB = $user->organizationMembershipFor($orgB);

        $this->assertNotNull($membershipA);
        $this->assertNotNull($membershipB);
        $this->assertSame($orgA->id, $membershipA->organization_id);
        $this->assertSame($orgB->id, $membershipB->organization_id);
    }

    public function test_one_user_holds_many_event_staff_memberships(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $org->id]);
        $event2 = Event::factory()->create(['organization_id' => $org->id]);

        EventStaffMembership::factory()->create([
            'event_id' => $event1->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::GuestManager->value,
        ]);
        EventStaffMembership::factory()->create([
            'event_id' => $event2->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::CheckinStaff->value,
        ]);

        $this->assertSame(2, $user->eventStaffMemberships()->count());
    }

    public function test_one_user_holds_many_event_owner_memberships(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $event1 = Event::factory()->create(['organization_id' => $org->id]);
        $event2 = Event::factory()->create(['organization_id' => $org->id]);

        EventOwnerMembership::factory()->groom()->create([
            'event_id' => $event1->id,
            'user_id' => $user->id,
        ]);
        EventOwnerMembership::factory()->bride()->create([
            'event_id' => $event2->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame(2, $user->eventOwnerMemberships()->count());
    }

    public function test_one_user_has_different_roles_across_orgs_and_events(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        OrganizationMembership::factory()->owner()->create([
            'organization_id' => $orgA->id,
            'user_id' => $user->id,
        ]);
        OrganizationMembership::factory()->create([
            'organization_id' => $orgB->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::Member->value,
        ]);

        // Different organisation roles.
        $this->assertTrue($user->isWorkspaceOwner($orgA));
        $this->assertFalse($user->isWorkspaceOwner($orgB));
        $this->assertSame(
            OrganizationRole::Member,
            $user->organizationMembershipFor($orgB)->role,
        );

        // Different event staff roles.
        $event1 = Event::factory()->create(['organization_id' => $orgA->id]);
        $event2 = Event::factory()->create(['organization_id' => $orgB->id]);

        EventStaffMembership::factory()->create([
            'event_id' => $event1->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::GuestManager->value,
        ]);
        EventStaffMembership::factory()->create([
            'event_id' => $event2->id,
            'user_id' => $user->id,
            'role' => EventStaffRole::CheckinStaff->value,
        ]);

        $this->assertSame(
            EventStaffRole::GuestManager,
            $user->eventStaffMemberships()->where('event_id', $event1->id)->first()->role,
        );
        $this->assertSame(
            EventStaffRole::CheckinStaff,
            $user->eventStaffMemberships()->where('event_id', $event2->id)->first()->role,
        );
    }

    public function test_guest_does_not_require_an_account_to_reach_the_landing_page(): void
    {
        $org = Organization::factory()->create();
        $event = Event::factory()->create(['organization_id' => $org->id]);

        // No authentication: the public landing route must still resolve.
        $response = $this->get(route('guest.landing', $event->slug));

        $response->assertOk();
    }
}
