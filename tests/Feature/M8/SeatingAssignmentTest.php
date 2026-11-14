<?php

namespace Tests\Feature\M8;

use App\Enums\OrganizationRole;
use App\Enums\SeatingAssignmentStatus;
use App\Models\FloorPlanVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\FloorPlanVersionService;
use App\Services\SeatingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M8\Concerns\BuildsSpatialFixtures;
use Tests\TestCase;

class SeatingAssignmentTest extends TestCase
{
    use BuildsSpatialFixtures, RefreshDatabase;

    private function seating(): SeatingAssignmentService
    {
        return app(SeatingAssignmentService::class);
    }

    private function versions(): FloorPlanVersionService
    {
        return app(FloorPlanVersionService::class);
    }

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    /**
     * @return array{0: FloorPlanVersion, 1: User}
     */
    private function draftWithTable(Organization $organization, int $capacity = 8): array
    {
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $document = $this->document([
            $this->tableObject('table_aaaaaaaa', ['properties' => ['capacity' => $capacity, 'table_number' => '1']]),
            $this->chairObject('chair_aaaaaaaa', 'table_aaaaaaaa', 0),
            $this->chairObject('chair_bbbbbbbb', 'table_aaaaaaaa', 1),
        ]);

        $version = $this->versions()->saveDocument($event, $version, $document, 0, $owner);

        return [$version, $owner];
    }

    public function test_a_named_individual_can_be_seated_on_a_chair(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 4);
        $member = $this->memberOfParty($guest, 'Budi');

        $assignment = $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $guest->id,
            'guest_party_membership_id' => $member->id,
        ], $owner);

        $this->assertSame(SeatingAssignmentStatus::Active, $assignment->status);
        $this->assertSame($member->id, $assignment->guest_party_membership_id);
        $this->assertSame(1, $assignment->headcount);
        $this->assertSame('chair_aaaaaaaa', $assignment->seat_object_id);
        $this->assertSame($version->id, $assignment->floor_plan_version_id);
        $this->assertSame($event->organization_id, $assignment->organization_id);
    }

    public function test_an_unidentified_party_slot_is_seated_as_a_headcount_placeholder(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 4);

        $assignment = $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $guest->id,
            'headcount' => 3,
        ], $owner);

        $this->assertNull($assignment->guest_party_membership_id);
        $this->assertSame(3, $assignment->headcount);
    }

    public function test_placeholder_headcount_cannot_exceed_the_party_quota(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 2);

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $guest->id,
            'headcount' => 3,
        ], $owner);
    }

    public function test_table_capacity_is_enforced_across_assignments(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization, 3);
        $event = $version->event;
        $first = $this->guestParty($event, 5);
        $second = $this->guestParty($event, 5);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $first->id,
            'headcount' => 2,
        ], $owner);

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $second->id,
            'headcount' => 2,
        ], $owner);
    }

    public function test_the_same_individual_cannot_be_seated_twice(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 4);
        $member = $this->memberOfParty($guest, 'Sari');

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $guest->id,
            'guest_party_membership_id' => $member->id,
        ], $owner);

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_bbbbbbbb',
            'guest_id' => $guest->id,
            'guest_party_membership_id' => $member->id,
        ], $owner);
    }

    public function test_a_seat_cannot_hold_two_assignments(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $first = $this->guestParty($event, 2);
        $second = $this->guestParty($event, 2);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $first->id,
            'guest_party_membership_id' => $this->memberOfParty($first)->id,
        ], $owner);

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $second->id,
            'guest_party_membership_id' => $this->memberOfParty($second)->id,
        ], $owner);
    }

    public function test_a_guest_from_another_event_cannot_be_seated(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $foreignGuest = $this->guestParty($this->eventIn($organization));

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $foreignGuest->id,
        ], $owner);
    }

    public function test_a_chair_from_another_table_cannot_be_used(): void
    {
        $organization = $this->organization();
        $event = $this->eventIn($organization);
        $owner = $this->owner($organization);
        $plan = $this->planFor($event, $owner);
        $version = $this->draftOf($plan);

        $document = $this->document([
            $this->tableObject('table_aaaaaaaa'),
            $this->tableObject('table_bbbbbbbb', ['x' => 600]),
            $this->chairObject('chair_aaaaaaaa', 'table_bbbbbbbb', 0),
        ]);

        $version = $this->versions()->saveDocument($event, $version, $document, 0, $owner);
        $guest = $this->guestParty($event, 2);

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $guest->id,
        ], $owner);
    }

    public function test_releasing_an_assignment_frees_the_seat(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $first = $this->guestParty($event, 2);
        $second = $this->guestParty($event, 2);

        $assignment = $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $first->id,
        ], $owner);

        $released = $this->seating()->release($event, $assignment, 'Tamu pindah meja', $owner);

        $this->assertSame(SeatingAssignmentStatus::Released, $released->status);
        $this->assertNotNull($released->released_at);
        $this->assertSame('Tamu pindah meja', $released->release_reason);

        $replacement = $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'seat_object_id' => 'chair_aaaaaaaa',
            'guest_id' => $second->id,
        ], $owner);

        $this->assertSame(SeatingAssignmentStatus::Active, $replacement->status);
    }

    public function test_table_summary_counts_chairs_and_assigned_heads(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 4);

        $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $guest->id,
            'headcount' => 3,
        ], $owner);

        $summary = $this->seating()->tableSummary($version->fresh());

        $this->assertArrayHasKey('table_aaaaaaaa', $summary);
        $this->assertSame(8, $summary['table_aaaaaaaa']['capacity']);
        $this->assertSame(3, $summary['table_aaaaaaaa']['assigned']);
        $this->assertSame(5, $summary['table_aaaaaaaa']['remaining']);
        $this->assertSame(2, $summary['table_aaaaaaaa']['chairs']);
    }

    public function test_seating_is_blocked_on_a_published_version(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 2);

        $this->versions()->publish($event, $version, $owner);

        $this->expectException(ValidationException::class);

        $this->seating()->assign($event, $version->fresh(), [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $guest->id,
        ], $owner);
    }

    public function test_removing_the_table_flags_the_assignment_for_review(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $guest = $this->guestParty($event, 2);

        $assignment = $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $guest->id,
        ], $owner);

        $emptied = $this->versions()->saveDocument($event, $version, $this->document(), 1, $owner);
        $flagged = $this->seating()->syncReviewStatus($event, $emptied);

        $this->assertSame(1, $flagged);
        $this->assertSame(SeatingAssignmentStatus::NeedsReview, $assignment->fresh()->status);
    }

    public function test_seating_endpoint_creates_an_assignment(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $plan = $version->floorPlan;
        $guest = $this->guestParty($event, 4);
        $member = $this->memberOfParty($guest, 'Dewi');

        $this->actingAs($owner)
            ->postJson(route('workspace.events.floor-plans.versions.seating.store', [$organization, $event, $plan, $version]), [
                'table_object_id' => 'table_aaaaaaaa',
                'seat_object_id' => 'chair_aaaaaaaa',
                'guest_id' => $guest->id,
                'guest_party_membership_id' => $member->id,
            ])
            ->assertCreated()
            ->assertJsonPath('assignment.guest_party_membership_id', $member->id)
            ->assertJsonPath('tables.table_aaaaaaaa.assigned', 1);
    }

    public function test_seating_endpoint_releases_an_assignment(): void
    {
        $organization = $this->organization();
        [$version, $owner] = $this->draftWithTable($organization);
        $event = $version->event;
        $plan = $version->floorPlan;
        $guest = $this->guestParty($event, 4);

        $assignment = $this->seating()->assign($event, $version, [
            'table_object_id' => 'table_aaaaaaaa',
            'guest_id' => $guest->id,
        ], $owner);

        $this->actingAs($owner)
            ->deleteJson(route('workspace.events.floor-plans.versions.seating.destroy', [
                $organization, $event, $plan, $version, $assignment,
            ]), ['reason' => 'Perubahan layout'])
            ->assertOk()
            ->assertJsonPath('released', true);

        $this->assertSame(SeatingAssignmentStatus::Released, $assignment->fresh()->status);
    }
}
