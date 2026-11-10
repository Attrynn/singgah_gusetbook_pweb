<?php

namespace Tests\Feature\M6\Concerns;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

trait BuildsOperationsFixtures
{
    protected function organization(string $slug = 'ops'): Organization
    {
        return Organization::factory()->create([
            'name' => 'Workspace '.$slug,
            'slug' => $slug.'-'.uniqid(),
            'status' => 'active',
        ]);
    }

    protected function memberOf(Organization $organization, OrganizationRole $role = OrganizationRole::Member): User
    {
        $user = User::factory()->owner()->create(['is_active' => true]);

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => OrganizationMembershipStatus::Active->value,
            'accepted_at' => now(),
        ]);

        return $user;
    }

    protected function eventIn(Organization $organization, array $attributes = []): Event
    {
        return Event::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'status' => 'published',
        ], $attributes));
    }

    /**
     * Active event staff membership for a fresh organization member.
     */
    protected function staffOf(
        Organization $organization,
        Event $event,
        EventStaffRole $role,
        MembershipStatus $status = MembershipStatus::Active,
    ): EventStaffMembership {
        $user = $this->memberOf($organization);

        return EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => $status->value,
            'accepted_at' => $status === MembershipStatus::Active ? now() : null,
        ]);
    }
}
