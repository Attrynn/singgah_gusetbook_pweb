<?php

namespace Tests\Feature\M8;

use App\Enums\OrganizationRole;
use App\Enums\VenueSpaceType;
use App\Enums\VenueStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\FloorPlanService;
use App\Services\VenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class VenueManagementTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function venues(): VenueService
    {
        return app(VenueService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_owner_creates_a_venue_with_address_and_location(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);

        $venue = $this->venues()->create($event, [
            'name' => 'Grand Ballroom',
            'address' => 'Jl. Merdeka 1',
            'google_maps_url' => 'https://maps.example.com/place',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'timezone' => 'Asia/Jakarta',
        ], $owner);

        $this->assertSame($organization->id, $venue->organization_id);
        $this->assertSame('Grand Ballroom', $venue->name);
        $this->assertSame(VenueStatus::Active, $venue->status);
        $this->assertSame($owner->id, $venue->created_by);
        $this->assertDatabaseHas('venues', [
            'id' => $venue->id,
            'organization_id' => $organization->id,
            'status' => VenueStatus::Active->value,
        ]);
    }

    public function test_venue_name_must_be_unique_inside_the_organization_only(): void
    {
        $organization = $this->organization('alpha');
        $other = $this->organization('beta');
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);

        $this->venues()->create($event, ['name' => 'Ballroom'], $owner);

        try {
            $this->venues()->create($event, ['name' => 'Ballroom'], $owner);
            $this->fail('Duplicate venue name was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }

        $otherEvent = $this->eventIn($other);
        $otherOwner = $this->owner($other);
        $created = $this->venues()->create($otherEvent, ['name' => 'Ballroom'], $otherOwner);

        $this->assertSame($other->id, $created->organization_id);
    }

    public function test_venue_listing_is_scoped_to_the_event_organization(): void
    {
        $organization = $this->organization('alpha');
        $other = $this->organization('beta');
        $event = $this->eventIn($organization);

        $this->venues()->create($event, ['name' => 'Alpha Hall'], $this->owner($organization));
        $this->venues()->create($this->eventIn($other), ['name' => 'Beta Hall'], $this->owner($other));

        $names = $this->venues()->listForEvent($event)->pluck('name')->all();

        $this->assertSame(['Alpha Hall'], $names);
    }

    public function test_archived_venue_cannot_be_updated(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $venue = $this->venueIn($organization);
        $this->venues()->archive($event, $venue, $owner);

        $this->expectException(ValidationException::class);

        $this->venues()->update($event, $venue->fresh(), ['name' => 'Renamed'], $owner);
    }

    public function test_venue_used_by_a_floor_plan_cannot_be_archived(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $venue = $this->venueIn($organization);

        app(FloorPlanService::class)->create($event, [
            'name' => 'Layout',
            'venue_id' => $venue->id,
        ], $owner);

        $this->expectException(ValidationException::class);

        $this->venues()->archive($event, $venue, $owner);
    }

    public function test_spaces_can_be_created_updated_and_archived(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $venue = $this->venueIn($organization);

        $space = $this->venues()->createSpace($event, $venue, [
            'name' => 'Ruang VIP',
            'type' => VenueSpaceType::VipRoom->value,
            'floor_label' => 'Lantai 2',
            'capacity' => 50,
            'sort_order' => 2,
        ], $owner);

        $this->assertSame(VenueSpaceType::VipRoom, $space->type);
        $this->assertSame(50, $space->capacity);
        $this->assertSame($organization->id, $space->organization_id);

        $updated = $this->venues()->updateSpace($event, $venue, $space, [
            'capacity' => 60,
            'name' => 'Ruang VIP Utama',
        ], $owner);

        $this->assertSame(60, $updated->capacity);
        $this->assertSame('Ruang VIP Utama', $updated->name);

        $archived = $this->venues()->archiveSpace($event, $venue, $updated, $owner);

        $this->assertSame(VenueStatus::Archived, $archived->status);
    }

    public function test_space_name_must_be_unique_per_venue(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $venue = $this->venueIn($organization);

        $this->venues()->createSpace($event, $venue, ['name' => 'Ballroom A'], $owner);

        $this->expectException(ValidationException::class);

        $this->venues()->createSpace($event, $venue, ['name' => 'Ballroom A'], $owner);
    }

    public function test_a_venue_from_another_organization_is_not_reachable(): void
    {
        $organization = $this->organization('alpha');
        $other = $this->organization('beta');
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $foreignVenue = $this->venueIn($other);

        $this->expectException(HttpException::class);

        $this->venues()->update($event, $foreignVenue, ['name' => 'Hijacked'], $owner);
    }

    public function test_regular_organization_member_cannot_manage_venues(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $member = $this->memberOf($organization, OrganizationRole::Member);

        $this->expectException(HttpException::class);

        $this->venues()->create($event, ['name' => 'Blocked'], $member);
    }

    public function test_venue_route_returns_the_management_page_for_an_authorized_owner(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);

        $this->actingAs($owner)
            ->get(route('workspace.events.venues.index', [$organization, $event]))
            ->assertOk()
            ->assertSee('venue', false);
    }
}
