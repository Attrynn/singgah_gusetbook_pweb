<?php

namespace Tests\Feature\M7;

use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorSubmissionStatus;
use App\Models\EventTask;
use App\Models\EventVendorDeliverableSubmission;
use App\Models\Organization;
use App\Models\User;
use App\Services\VendorDeliverableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\M7\Concerns\BuildsVendorFixtures;
use Tests\TestCase;

class VendorDeliverableTest extends TestCase
{
    use BuildsVendorFixtures, RefreshDatabase;

    private function owner(Organization $organization): User
    {
        return $this->memberOf($organization, OrganizationRole::Owner);
    }

    public function test_deliverable_can_be_submitted_and_accepted(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.deliverables.store', [$org, $event, $engagement]), [
                'title' => 'Dekorasi altar',
                'due_at' => now()->addDays(3)->toDateTimeString(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $deliverable = $engagement->deliverables()->firstOrFail();
        $this->assertSame(VendorDeliverableStatus::NotStarted, $deliverable->status);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.deliverables.submissions.store', [$org, $event, $deliverable]), [
                'notes' => 'Foto hasil terpasang.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $deliverable->refresh();
        $this->assertSame(VendorDeliverableStatus::Submitted, $deliverable->status);

        $submission = $deliverable->submissions()->firstOrFail();

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.deliverables.submissions.review', [$org, $event, $deliverable, $submission]), [
                'status' => VendorSubmissionStatus::Accepted->value,
                'notes' => 'Sesuai brief.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $deliverable->refresh();
        $this->assertSame(VendorDeliverableStatus::Accepted, $deliverable->status);
        $this->assertSame($owner->id, $deliverable->accepted_by);
        $this->assertNotNull($deliverable->accepted_at);
        $this->assertSame(VendorSubmissionStatus::Accepted, $submission->refresh()->status);
    }

    public function test_rejection_requires_notes_and_is_preserved_in_submission_history(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $deliverable = $this->deliverableFor($engagement);
        $service = app(VendorDeliverableService::class);
        $submission = $service->submit($deliverable, ['notes' => 'Versi pertama'], $owner);

        $this->expectException(ValidationException::class);

        $service->review($submission, VendorSubmissionStatus::Rejected, $owner, '   ');
    }

    public function test_rejection_history_keeps_every_submission(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $deliverable = $this->deliverableFor($engagement);
        $service = app(VendorDeliverableService::class);

        $first = $service->submit($deliverable, ['notes' => 'Versi pertama'], $owner);
        $service->review($first, VendorSubmissionStatus::Rejected, $owner, 'Warna tidak sesuai.');

        $deliverable->refresh();
        $this->assertSame(VendorDeliverableStatus::Rejected, $deliverable->status);

        $second = $service->submit($deliverable, ['notes' => 'Versi perbaikan'], $owner);
        $service->review($second, VendorSubmissionStatus::Accepted, $owner, 'Sudah sesuai.');

        $this->assertSame(2, EventVendorDeliverableSubmission::query()
            ->where('event_vendor_deliverable_id', $deliverable->id)
            ->count());

        $this->assertSame(
            VendorSubmissionStatus::Rejected,
            EventVendorDeliverableSubmission::query()->findOrFail($first->id)->status,
        );
        $this->assertSame(
            'Warna tidak sesuai.',
            EventVendorDeliverableSubmission::query()->findOrFail($first->id)->review_notes,
        );
    }

    public function test_overdue_helper_only_applies_to_open_deliverables(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);

        $open = $this->deliverableFor($engagement, [
            'due_at' => now()->subDay(),
            'status' => VendorDeliverableStatus::InProgress->value,
        ]);
        $accepted = $this->deliverableFor($engagement, [
            'due_at' => now()->subDay(),
            'status' => VendorDeliverableStatus::Accepted->value,
        ]);

        $this->assertTrue($open->isOverdue());
        $this->assertFalse($accepted->isOverdue());
    }

    public function test_linking_a_task_from_another_event_is_rejected(): void
    {
        $org = $this->organization();
        $eventA = $this->eventIn($org);
        $eventB = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($eventA, $vendor);
        $deliverable = $this->deliverableFor($engagement);

        $foreignTask = EventTask::create([
            'event_id' => $eventB->id,
            'title' => 'Tugas acara lain',
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('workspace.events.vendors.deliverables.task', [$org, $eventA, $deliverable]), [
                'event_task_id' => $foreignTask->id,
            ])
            ->assertSessionHasErrors('event_task_id');

        $this->assertNull($deliverable->refresh()->internal_task_id);
    }

    public function test_staff_without_review_permission_cannot_accept_deliverables(): void
    {
        $org = $this->organization();
        $event = $this->eventIn($org);
        $vendor = $this->vendorIn($org);
        $owner = $this->owner($org);
        $engagement = $this->confirmedEngagementFor($event, $vendor);
        $deliverable = $this->deliverableFor($engagement, [
            'status' => VendorDeliverableStatus::Submitted->value,
        ]);

        $generalStaff = $this->staffOf($org, $event, EventStaffRole::GeneralStaff);

        $this->actingAs($generalStaff->user)
            ->post(route('workspace.events.vendors.deliverables.status', [$org, $event, $deliverable]), [
                'status' => VendorDeliverableStatus::Accepted->value,
            ])
            ->assertForbidden();

        $this->assertSame(VendorDeliverableStatus::Submitted, $deliverable->refresh()->status);
        $this->assertNull($deliverable->accepted_by);
    }
}
