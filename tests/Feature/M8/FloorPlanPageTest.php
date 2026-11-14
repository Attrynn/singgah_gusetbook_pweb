<?php

namespace Tests\Feature\M8;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\FloorPlanVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class FloorPlanPageTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_floor_plan_index_renders_for_an_authorized_owner(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner, ['name' => 'Layout Resepsi']);

        $this->actingAs($owner)
            ->get(route('workspace.events.floor-plans.index', [$organization, $event]))
            ->assertOk()
            ->assertSee('Layout Resepsi')
            ->assertSee(route('workspace.events.floor-plans.editor', [$organization, $event, $plan]), false);
    }

    public function test_floor_plan_show_lists_version_history(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);

        $version = $this->draftOf($plan);
        $version = app(FloorPlanVersionService::class)->saveDocument(
            $event,
            $version,
            $this->document([$this->tableObject('table_aaaaaaaa')]),
            0,
            $owner,
        );
        app(FloorPlanVersionService::class)->publish($event, $version, $owner);

        $this->actingAs($owner)
            ->get(route('workspace.events.floor-plans.show', [$organization, $event, $plan]))
            ->assertOk()
            ->assertSee('Diterbitkan')
            ->assertSee('v1');
    }

    public function test_floor_plan_index_is_forbidden_without_view_permission(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $this->planFor($event, $this->owner($organization));
        $guestRelations = $this->staffOf($organization, $event, EventStaffRole::GuestRelations)->user;

        $this->actingAs($guestRelations)
            ->get(route('workspace.events.floor-plans.index', [$organization, $event]))
            ->assertForbidden();
    }

    public function test_floor_plan_editor_renders_in_edit_mode_for_an_owner(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $this->draftOf($plan);

        $this->actingAs($owner)
            ->get(route('workspace.events.floor-plans.editor', [$organization, $event, $plan]))
            ->assertOk()
            ->assertSee('id="floorplan-canvas"', false)
            ->assertSee('window.__floorPlanEditor', false)
            ->assertSee('"readOnly":false', false)
            ->assertSee('"canManage":true', false)
            ->assertSee('"canPublish":true', false);
    }

    public function test_floor_plan_editor_is_read_only_for_view_only_staff(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $this->draftOf($plan);
        $usher = $this->staffOf($organization, $event, EventStaffRole::SeatingUsher)->user;

        $this->actingAs($usher)
            ->get(route('workspace.events.floor-plans.editor', [$organization, $event, $plan]))
            ->assertOk()
            ->assertSee('"readOnly":true', false)
            ->assertSee('"canManage":false', false)
            ->assertSee('"canPublish":false', false);
    }

    public function test_floor_plan_editor_is_forbidden_without_view_permission(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $this->draftOf($plan);
        $guestRelations = $this->staffOf($organization, $event, EventStaffRole::GuestRelations)->user;

        $this->actingAs($guestRelations)
            ->get(route('workspace.events.floor-plans.editor', [$organization, $event, $plan]))
            ->assertForbidden();
    }

    public function test_floor_plan_show_returns_not_found_for_another_event(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $otherEvent = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $foreignPlan = $this->planFor($otherEvent, $owner);

        $this->actingAs($owner)
            ->get(route('workspace.events.floor-plans.show', [$organization, $event, $foreignPlan]))
            ->assertNotFound();
    }
}
