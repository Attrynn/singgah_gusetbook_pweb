<?php

namespace Tests\Feature\M8;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\FloorPlanVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class SpatialSecurityTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_event_from_another_organization_is_not_reachable(): void
    {
        $organization = $this->organization('alpha');
        $other = $this->organization('beta');
        $event = $this->eventIn($organization);
        $plan = $this->planFor($event, $this->owner($organization));
        $version = $this->draftOf($plan);
        $otherOwner = $this->owner($other);

        $this->actingAs($otherOwner)
            ->getJson(route('workspace.events.floor-plans.versions.show', [$other, $event, $plan, $version]))
            ->assertNotFound();
    }

    public function test_floor_plan_from_another_event_is_not_reachable(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $otherEvent = $this->eventIn($organization);
        $owner = $this->owner($organization);

        $plan = $this->planFor($otherEvent, $owner);
        $version = $this->draftOf($plan);

        $this->actingAs($owner)
            ->putJson(route('workspace.events.floor-plans.versions.document', [$organization, $event, $plan, $version]), [
                'document' => $this->document(),
                'lock_version' => 0,
            ])
            ->assertNotFound();
    }

    public function test_a_staff_member_without_manage_permission_cannot_save_the_document(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);
        $observer = $this->staffOf($organization, $event, EventStaffRole::Observer)->user;

        $this->actingAs($observer)
            ->putJson(route('workspace.events.floor-plans.versions.document', [$organization, $event, $plan, $version]), [
                'document' => $this->document(),
                'lock_version' => 0,
            ])
            ->assertForbidden();
    }

    public function test_a_staff_member_with_view_permission_can_read_the_version(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);
        $observer = $this->staffOf($organization, $event, EventStaffRole::Observer)->user;

        $this->actingAs($observer)
            ->getJson(route('workspace.events.floor-plans.versions.show', [$organization, $event, $plan, $version]))
            ->assertOk()
            ->assertJsonPath('version.version_number', 1)
            ->assertJsonPath('editable', true);
    }

    public function test_a_seating_usher_cannot_publish_but_can_assign_seating(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $document = $this->document([$this->tableObject('table_aaaaaaaa')]);
        $version = app(FloorPlanVersionService::class)
            ->saveDocument($event, $version, $document, 0, $owner);

        $usher = $this->staffOf($organization, $event, EventStaffRole::SeatingUsher)->user;
        $guest = $this->guestParty($event, 2);

        $this->actingAs($usher)
            ->postJson(route('workspace.events.floor-plans.versions.publish', [$organization, $event, $plan, $version]))
            ->assertForbidden();

        $this->actingAs($usher)
            ->postJson(route('workspace.events.floor-plans.versions.seating.store', [$organization, $event, $plan, $version]), [
                'table_object_id' => 'table_aaaaaaaa',
                'guest_id' => $guest->id,
            ])
            ->assertCreated();
    }

    public function test_regular_organization_member_cannot_create_a_floor_plan(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $member = $this->memberOf($organization, OrganizationRole::Member);

        $this->actingAs($member)
            ->post(route('workspace.events.floor-plans.store', [$organization, $event]), ['name' => 'Blocked'])
            ->assertForbidden();
    }

    public function test_guest_role_without_floor_plan_permission_cannot_view_versions(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $guestRelations = $this->staffOf($organization, $event, EventStaffRole::GuestRelations)->user;

        $this->actingAs($guestRelations)
            ->getJson(route('workspace.events.floor-plans.versions.show', [$organization, $event, $plan, $version]))
            ->assertForbidden();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $this->getJson(route('workspace.events.floor-plans.versions.show', [$organization, $event, $plan, $version]))
            ->assertUnauthorized();
    }

    public function test_m8_permissions_are_distinct_and_role_scoped(): void
    {
        $permissions = [
            PermissionRegistry::VENUE_VIEW,
            PermissionRegistry::VENUE_MANAGE,
            PermissionRegistry::FLOOR_PLAN_VIEW,
            PermissionRegistry::FLOOR_PLAN_MANAGE,
            PermissionRegistry::FLOOR_PLAN_PUBLISH,
            PermissionRegistry::FLOOR_PLAN_DELETE,
            PermissionRegistry::FLOOR_PLAN_VERSION,
            PermissionRegistry::SEATING_VIEW,
            PermissionRegistry::SEATING_MANAGE,
            PermissionRegistry::SEATING_ASSIGN,
        ];

        $this->assertSame($permissions, array_values(array_unique($permissions)));

        $ownerPermissions = PermissionRegistry::forWorkspaceOwner();
        foreach ($permissions as $permission) {
            $this->assertContains($permission, $ownerPermissions);
        }

        $observer = PermissionRegistry::forEventStaffRole(EventStaffRole::Observer);
        $this->assertContains(PermissionRegistry::FLOOR_PLAN_VIEW, $observer);
        $this->assertContains(PermissionRegistry::VENUE_VIEW, $observer);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_MANAGE, $observer);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_PUBLISH, $observer);
        $this->assertNotContains(PermissionRegistry::VENUE_MANAGE, $observer);

        $usher = PermissionRegistry::forEventStaffRole(EventStaffRole::SeatingUsher);
        $this->assertContains(PermissionRegistry::SEATING_ASSIGN, $usher);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_PUBLISH, $usher);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_MANAGE, $usher);

        $delegatable = PermissionRegistry::delegatableEventPermissions();
        $this->assertContains(PermissionRegistry::FLOOR_PLAN_VIEW, $delegatable);
        $this->assertContains(PermissionRegistry::SEATING_ASSIGN, $delegatable);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_MANAGE, $delegatable);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_PUBLISH, $delegatable);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_DELETE, $delegatable);
        $this->assertNotContains(PermissionRegistry::FLOOR_PLAN_VERSION, $delegatable);
    }
}
