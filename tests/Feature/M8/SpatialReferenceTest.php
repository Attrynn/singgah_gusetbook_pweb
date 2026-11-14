<?php

namespace Tests\Feature\M8;

use App\Enums\OrganizationRole;
use App\Enums\SpatialReferenceStatus;
use App\Models\Event;
use App\Models\EventStation;
use App\Models\FloorPlanVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\FloorPlanVersionService;
use App\Services\SpatialReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class SpatialReferenceTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function spatial(): SpatialReferenceService
    {
        return app(SpatialReferenceService::class);
    }

    private function versions(): FloorPlanVersionService
    {
        return app(FloorPlanVersionService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    private function stationFor(Event $event, string $name = 'Registrasi'): EventStation
    {
        return EventStation::create([
            'event_id' => $event->id,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'category' => 'general',
            'status' => 'draft',
        ]);
    }

    /**
     * @return array{0: FloorPlanVersion, 1: User}
     */
    private function draftWithObjects(Organization $organization): array
    {
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $document = $this->document([
            $this->tableObject('table_aaaaaaaa'),
            $this->tableObject('table_bbbbbbbb', ['x' => 600]),
        ]);

        return [$this->versions()->saveDocument($event, $version, $document, 0, $owner), $owner];
    }

    public function test_an_m6_station_can_be_linked_to_a_layout_object(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $station = $this->stationFor($event);

        $reference = $this->spatial()->attach($event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
            'label' => 'Meja registrasi',
        ], $owner);

        $this->assertSame(EventStation::class, $reference->referenceable_type);
        $this->assertSame($station->id, $reference->referenceable_id);
        $this->assertSame('table_aaaaaaaa', $reference->object_id);
        $this->assertSame(SpatialReferenceStatus::Active, $reference->status);
        $this->assertSame($event->id, $reference->event_id);
        $this->assertSame($version->id, $reference->floor_plan_version_id);
        $this->assertDatabaseHas('spatial_references', [
            'id' => $reference->id,
            'referenceable_type' => EventStation::class,
            'referenceable_id' => $station->id,
        ]);
    }

    public function test_the_reference_target_must_belong_to_the_same_event(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $foreignStation = $this->stationFor($this->eventIn($organization), 'Lain');

        $this->expectException(ValidationException::class);

        $this->spatial()->attach($event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'station',
            'referenceable_id' => $foreignStation->id,
        ], $owner);
    }

    public function test_an_unknown_reference_target_type_is_rejected(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);

        $this->expectException(ValidationException::class);

        $this->spatial()->attach($version->event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'invoice',
            'referenceable_id' => 1,
        ], $owner);
    }

    public function test_an_object_that_does_not_exist_in_the_version_is_rejected(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $station = $this->stationFor($event);

        $this->expectException(ValidationException::class);

        $this->spatial()->attach($event, $version, [
            'object_id' => 'table_zzzzzzzz',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
        ], $owner);
    }

    public function test_relinking_the_same_target_updates_the_existing_reference(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $station = $this->stationFor($event);

        $first = $this->spatial()->attach($event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
        ], $owner);

        $second = $this->spatial()->attach($event, $version, [
            'object_id' => 'table_bbbbbbbb',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
        ], $owner);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('table_bbbbbbbb', $second->object_id);
        $this->assertDatabaseCount('spatial_references', 1);
    }

    public function test_a_reference_can_be_looked_up_by_target(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $station = $this->stationFor($event);

        $this->spatial()->attach($event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
        ], $owner);

        $found = $this->spatial()->forTarget($event, 'station', $station->id);

        $this->assertNotNull($found);
        $this->assertSame('table_aaaaaaaa', $found->object_id);
    }

    public function test_detaching_removes_the_reference(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $station = $this->stationFor($event);

        $reference = $this->spatial()->attach($event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
        ], $owner);

        $this->spatial()->detach($event, $reference, $owner);

        $this->assertDatabaseMissing('spatial_references', ['id' => $reference->id]);
    }

    public function test_a_reference_is_flagged_when_its_object_is_removed(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithObjects($organization);
        $event = $version->event;
        $station = $this->stationFor($event);

        $reference = $this->spatial()->attach($event, $version, [
            'object_id' => 'table_aaaaaaaa',
            'referenceable_type' => 'station',
            'referenceable_id' => $station->id,
        ], $owner);

        $emptied = $this->versions()->saveDocument($event, $version, $this->document(), 1, $owner);
        $flagged = $this->spatial()->syncReviewStatus($emptied);

        $this->assertSame(1, $flagged);
        $this->assertSame(SpatialReferenceStatus::NeedsReview, $reference->fresh()->status);
    }
}
