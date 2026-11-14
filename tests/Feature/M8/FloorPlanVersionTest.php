<?php

namespace Tests\Feature\M8;

use App\Enums\FloorPlanStatus;
use App\Enums\FloorPlanVersionStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use App\Services\FloorPlanService;
use App\Services\FloorPlanVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class FloorPlanVersionTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function plans(): FloorPlanService
    {
        return app(FloorPlanService::class);
    }

    private function versions(): FloorPlanVersionService
    {
        return app(FloorPlanVersionService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_creating_a_floor_plan_also_creates_version_one_draft(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);

        $plan = $this->plans()->create($event, [
            'name' => 'Layout Utama',
            'description' => '<b>Deskripsi</b>',
            'canvas_width' => 2400,
            'canvas_height' => 1600,
            'grid_size' => 25,
        ], $owner);

        $version = $this->draftOf($plan);

        $this->assertSame(1, $version->version_number);
        $this->assertSame(FloorPlanVersionStatus::Draft, $version->status);
        $this->assertSame(2400.0, (float) $version->canvas_width);
        $this->assertSame(1600.0, (float) $version->canvas_height);
        $this->assertSame(25.0, (float) $version->grid_size);
        $this->assertSame(0, $version->object_count);
        $this->assertSame('Deskripsi', $plan->description);
        $this->assertSame(FloorPlanStatus::Active, $plan->status);
    }

    public function test_floor_plan_names_are_unique_per_event(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);

        $this->plans()->create($event, ['name' => 'Layout'], $owner);

        $this->expectException(ValidationException::class);

        $this->plans()->create($event, ['name' => 'Layout'], $owner);
    }

    public function test_saving_a_document_updates_stats_and_lock_version(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $document = $this->document([
            $this->tableObject('table_aaaaaaaa', ['properties' => ['capacity' => 8, 'table_number' => '1']]),
            $this->chairObject('chair_aaaaaaaa', 'table_aaaaaaaa', 0),
            $this->chairObject('chair_bbbbbbbb', 'table_aaaaaaaa', 1),
        ], [
            [
                'id' => 'zone_aaaaaaaa',
                'type' => 'vip',
                'name' => 'Area VIP',
                'geometry' => ['kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 500, 'height' => 400],
            ],
        ]);

        $saved = $this->versions()->saveDocument($event, $version, $document, 0, $owner);

        $this->assertSame(1, $saved->lock_version);
        $this->assertSame(3, $saved->object_count);
        $this->assertSame(1, $saved->table_count);
        $this->assertSame(2, $saved->chair_count);
        $this->assertSame(1, $saved->zone_count);
        $this->assertSame(8, $saved->seat_capacity);
    }

    public function test_stale_lock_version_is_rejected_with_conflict(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        try {
            $this->versions()->saveDocument($event, $version, $this->document(), 99, $owner);
            $this->fail('Stale lock version was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_published_version_is_immutable(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $this->versions()->publish($event, $version, $owner);
        $published = $version->fresh();

        $this->assertSame(FloorPlanVersionStatus::Published, $published->status);
        $this->assertSame($owner->id, $published->published_by);
        $this->assertNotNull($published->published_at);

        $this->expectException(ValidationException::class);

        $this->versions()->saveDocument($event, $published, $this->document(), $published->lock_version, $owner);
    }

    public function test_publish_is_blocked_when_the_layout_has_errors(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $document = $this->document([
            $this->tableObject('table_aaaaaaaa', ['x' => -900, 'y' => 10]),
        ]);

        $version = $this->versions()->saveDocument($event, $version, $document, 0, $owner);

        try {
            $this->versions()->publish($event, $version, $owner);
            $this->fail('Publish accepted a layout with blocking errors.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('document', $exception->errors());
        }

        $this->assertSame(FloorPlanVersionStatus::Draft, $version->fresh()->status);
    }

    public function test_a_new_draft_copies_the_published_version(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $draft = $this->draftOf($plan);

        $draft = $this->versions()->saveDocument(
            $event,
            $draft,
            $this->document([$this->tableObject('table_aaaaaaaa')]),
            0,
            $owner,
        );

        $this->versions()->publish($event, $draft, $owner);

        $next = $this->versions()->createNextDraft($event, $plan, $owner);

        $this->assertSame(2, $next->version_number);
        $this->assertSame(FloorPlanVersionStatus::Draft, $next->status);
        $this->assertSame(1, $next->object_count);
        $this->assertSame('table_aaaaaaaa', $next->document['objects'][0]['id']);
    }

    public function test_only_one_active_draft_is_allowed_per_plan(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);

        $this->expectException(ValidationException::class);

        $this->versions()->createDraft($event, $plan, $owner);
    }

    public function test_archiving_a_version_marks_it_archived(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $archived = $this->versions()->archive($event, $version, $owner);

        $this->assertSame(FloorPlanVersionStatus::Archived, $archived->status);
        $this->assertSame($owner->id, $archived->archived_by);
        $this->assertNotNull($archived->archived_at);
    }

    public function test_a_plan_with_a_published_version_cannot_be_deleted(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);

        $this->versions()->publish($event, $this->draftOf($plan), $owner);

        $this->expectException(ValidationException::class);

        $this->plans()->delete($event, $plan, $owner);
    }

    public function test_a_draft_only_plan_can_be_deleted(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);

        $this->plans()->delete($event, $plan, $owner);

        $this->assertDatabaseMissing('floor_plans', ['id' => $plan->id]);
        $this->assertDatabaseMissing('floor_plan_versions', ['floor_plan_id' => $plan->id]);
    }

    public function test_archived_plan_cannot_be_updated(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);

        $this->plans()->archive($event, $plan, $owner);

        $this->expectException(ValidationException::class);

        $this->plans()->update($event, $plan->fresh(), ['name' => 'Renamed'], $owner);
    }

    public function test_document_endpoint_saves_and_returns_derived_stats(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $this->actingAs($owner)
            ->putJson(route('workspace.events.floor-plans.versions.document', [$organization, $event, $plan, $version]), [
                'document' => $this->document([$this->tableObject('table_aaaaaaaa')]),
                'lock_version' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('saved', true)
            ->assertJsonPath('lock_version', 1)
            ->assertJsonPath('stats.table_count', 1)
            ->assertJsonPath('stats.seat_capacity', 8);
    }

    public function test_document_endpoint_reports_a_lock_conflict(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $this->actingAs($owner)
            ->putJson(route('workspace.events.floor-plans.versions.document', [$organization, $event, $plan, $version]), [
                'document' => $this->document(),
                'lock_version' => 5,
            ])
            ->assertStatus(409);
    }
}
