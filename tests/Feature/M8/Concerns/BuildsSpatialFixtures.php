<?php

namespace Tests\Feature\M8\Concerns;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\FloorPlan;
use App\Models\FloorPlanVersion;
use App\Models\Guest;
use App\Models\GuestPartyMembership;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Models\Venue;
use App\Services\FloorPlanService;

trait BuildsSpatialFixtures
{
    protected function organization(string $slug = 'spatial'): Organization
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

    protected function ownerOf(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    protected function eventIn(Organization $organization, array $attributes = []): Event
    {
        return Event::factory()->create(array_merge([
            'organization_id' => $organization->id,
            'status' => 'published',
        ], $attributes));
    }

    protected function staffOf(Organization $organization, Event $event, EventStaffRole $role): EventStaffMembership
    {
        $user = $this->memberOf($organization);

        return EventStaffMembership::factory()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => MembershipStatus::Active->value,
            'accepted_at' => now(),
        ]);
    }

    protected function venueIn(Organization $organization, array $attributes = []): Venue
    {
        return Venue::create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Venue '.uniqid(),
            'status' => 'active',
        ], $attributes));
    }

    protected function planFor(Event $event, User $actor, array $attributes = []): FloorPlan
    {
        return app(FloorPlanService::class)->create($event, array_merge([
            'name' => 'Layout '.uniqid(),
        ], $attributes), $actor);
    }

    protected function draftOf(FloorPlan $plan): FloorPlanVersion
    {
        return $plan->versions()->where('status', 'draft')->firstOrFail();
    }

    protected function guestParty(Event $event, int $quota = 4, array $attributes = []): Guest
    {
        return Guest::factory()->create(array_merge([
            'event_id' => $event->id,
            'max_attendees' => $quota,
        ], $attributes));
    }

    protected function memberOfParty(Guest $guest, string $name = 'Anggota'): GuestPartyMembership
    {
        return GuestPartyMembership::create([
            'guest_id' => $guest->id,
            'event_id' => $guest->event_id,
            'name' => $name.' '.uniqid(),
            'status' => 'active',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function tableObject(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => 'round_table',
            'x' => 200,
            'y' => 200,
            'width' => 180,
            'height' => 180,
            'rotation' => 0,
            'z_index' => 0,
            'visible' => true,
            'locked' => false,
            'label' => 'Meja 1',
            'group_id' => null,
            'parent_id' => null,
            'properties' => [
                'capacity' => 8,
                'table_number' => '1',
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function chairObject(string $id, string $tableId, int $seatIndex = 0, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => 'chair',
            'x' => 200,
            'y' => 380,
            'width' => 45,
            'height' => 45,
            'rotation' => 0,
            'z_index' => 1,
            'visible' => true,
            'locked' => false,
            'label' => 'Kursi 1',
            'group_id' => null,
            'parent_id' => $tableId,
            'properties' => [
                'table_id' => $tableId,
                'seat_index' => $seatIndex,
            ],
        ], $overrides);
    }

    /**
     * @param  array<int, array<string, mixed>>  $objects
     * @param  array<int, array<string, mixed>>  $zones
     * @return array<string, mixed>
     */
    protected function document(array $objects = [], array $zones = [], array $canvas = []): array
    {
        return [
            'schema_version' => 1,
            'canvas' => array_merge([
                'width' => 2000,
                'height' => 1500,
                'unit' => 'cm',
                'grid_size' => 50,
                'grid_visible' => true,
                'snap_enabled' => true,
                'background_color' => '#ffffff',
            ], $canvas),
            'objects' => $objects,
            'zones' => $zones,
        ];
    }
}
