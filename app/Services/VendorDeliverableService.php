<?php

namespace App\Services;

use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorSubmissionStatus;
use App\Models\EventStaffMembership;
use App\Models\EventTask;
use App\Models\EventVendorDeliverable;
use App\Models\EventVendorDeliverableSubmission;
use App\Models\EventVendorEngagement;
use App\Models\User;
use App\Models\VendorContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Vendor deliverables: external service obligations, their submissions and the
 * internal review that accepts or rejects them. Vendor completion never implies
 * organizational acceptance.
 */
class VendorDeliverableService
{
    public function __construct(private readonly AuditService $audit) {}

    public function create(EventVendorEngagement $engagement, array $data, User $actor): EventVendorDeliverable
    {
        $this->assertReviewerBelongs($engagement, $data['reviewer_membership_id'] ?? null);
        $this->assertContactBelongs($engagement, $data['vendor_contact_id'] ?? null);

        $deliverable = EventVendorDeliverable::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'vendor_contact_id' => $data['vendor_contact_id'] ?? null,
            'reviewer_membership_id' => $data['reviewer_membership_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'status' => VendorDeliverableStatus::NotStarted->value,
            'sequence' => $data['sequence'] ?? ((int) $engagement->deliverables()->max('sequence') + 1),
        ]);

        $this->audit->record(
            'vendor.deliverable.created',
            "Deliverable vendor dibuat: {$deliverable->title}",
            $engagement->event,
            $actor,
            $deliverable,
        );

        return $deliverable;
    }

    public function update(EventVendorDeliverable $deliverable, array $data, User $actor): EventVendorDeliverable
    {
        if ($deliverable->status === VendorDeliverableStatus::Accepted) {
            throw ValidationException::withMessages([
                'deliverable' => 'Deliverable yang sudah diterima tidak dapat diubah.',
            ]);
        }

        $this->assertReviewerBelongs($deliverable->engagement, $data['reviewer_membership_id'] ?? null);
        $this->assertContactBelongs($deliverable->engagement, $data['vendor_contact_id'] ?? null);

        $deliverable->forceFill(array_intersect_key($data, array_flip([
            'title', 'description', 'due_at', 'reviewer_membership_id', 'vendor_contact_id', 'sequence',
        ])))->save();

        $this->audit->record(
            'vendor.deliverable.updated',
            "Deliverable vendor diperbarui: {$deliverable->title}",
            $deliverable->event,
            $actor,
            $deliverable,
        );

        return $deliverable->refresh();
    }

    public function transition(
        EventVendorDeliverable $deliverable,
        VendorDeliverableStatus $target,
        User $actor,
        ?string $notes = null,
    ): EventVendorDeliverable {
        if (! $deliverable->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status deliverable tidak valid.',
            ]);
        }

        if ($target === VendorDeliverableStatus::Rejected && trim((string) $notes) === '') {
            throw ValidationException::withMessages([
                'notes' => 'Alasan penolakan deliverable wajib diisi.',
            ]);
        }

        $attributes = ['status' => $target->value];

        if ($target === VendorDeliverableStatus::Accepted) {
            $attributes['accepted_by'] = $actor->id;
            $attributes['accepted_at'] = now();
            $attributes['acceptance_notes'] = $notes;
        }

        $deliverable->forceFill($attributes)->save();

        $this->audit->recordCritical(
            'vendor.deliverable.transitioned',
            "Status deliverable vendor: {$deliverable->title} → {$target->value}",
            $deliverable->event,
            $actor,
            $deliverable,
        );

        return $deliverable->refresh();
    }

    public function submit(EventVendorDeliverable $deliverable, array $data, User $actor): EventVendorDeliverableSubmission
    {
        if (! $deliverable->status->canTransitionTo(VendorDeliverableStatus::Submitted)) {
            throw ValidationException::withMessages([
                'deliverable' => 'Deliverable ini tidak dapat diserahkan pada status saat ini.',
            ]);
        }

        return DB::transaction(function () use ($deliverable, $data, $actor): EventVendorDeliverableSubmission {
            $submission = EventVendorDeliverableSubmission::create([
                'event_vendor_deliverable_id' => $deliverable->id,
                'submitted_by_user_id' => $actor->id,
                'notes' => $data['notes'] ?? null,
                'event_vendor_document_id' => $data['event_vendor_document_id'] ?? null,
                'status' => VendorSubmissionStatus::Submitted->value,
                'submitted_at' => now(),
            ]);

            $deliverable->forceFill(['status' => VendorDeliverableStatus::Submitted])->save();

            $this->audit->record(
                'vendor.deliverable.submitted',
                "Deliverable vendor diserahkan: {$deliverable->title}",
                $deliverable->event,
                $actor,
                $submission,
            );

            return $submission;
        });
    }

    public function review(
        EventVendorDeliverableSubmission $submission,
        VendorSubmissionStatus $target,
        User $actor,
        ?string $notes = null,
    ): EventVendorDeliverableSubmission {
        $allowed = [
            VendorSubmissionStatus::Submitted->value => [
                VendorSubmissionStatus::UnderReview,
                VendorSubmissionStatus::Accepted,
                VendorSubmissionStatus::Rejected,
                VendorSubmissionStatus::ClarificationRequested,
            ],
            VendorSubmissionStatus::UnderReview->value => [
                VendorSubmissionStatus::Accepted,
                VendorSubmissionStatus::Rejected,
                VendorSubmissionStatus::ClarificationRequested,
            ],
            VendorSubmissionStatus::ClarificationRequested->value => [
                VendorSubmissionStatus::UnderReview,
                VendorSubmissionStatus::Rejected,
            ],
        ];

        $current = $submission->status->value;

        if (! in_array($target, $allowed[$current] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status penyerahan tidak valid.',
            ]);
        }

        if ($target === VendorSubmissionStatus::Rejected && trim((string) $notes) === '') {
            throw ValidationException::withMessages([
                'notes' => 'Alasan penolakan wajib diisi.',
            ]);
        }

        $deliverable = $submission->deliverable;

        return DB::transaction(function () use ($submission, $target, $actor, $notes, $deliverable): EventVendorDeliverableSubmission {
            $submission->forceFill([
                'status' => $target,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ])->save();

            $deliverableStatus = match ($target) {
                VendorSubmissionStatus::Accepted => VendorDeliverableStatus::Accepted,
                VendorSubmissionStatus::Rejected => VendorDeliverableStatus::Rejected,
                VendorSubmissionStatus::ClarificationRequested => VendorDeliverableStatus::InProgress,
                VendorSubmissionStatus::UnderReview => VendorDeliverableStatus::UnderReview,
                default => $deliverable->status,
            };

            $attributes = ['status' => $deliverableStatus->value];

            if ($deliverableStatus === VendorDeliverableStatus::Accepted) {
                $attributes['accepted_by'] = $actor->id;
                $attributes['accepted_at'] = now();
                $attributes['acceptance_notes'] = $notes;
            }

            $deliverable->forceFill($attributes)->save();

            $this->audit->recordCritical(
                'vendor.deliverable.reviewed',
                "Penyerahan deliverable ditinjau: {$deliverable->title} → {$target->value}",
                $deliverable->event,
                $actor,
                $submission,
            );

            return $submission->refresh();
        });
    }

    public function linkTask(EventVendorDeliverable $deliverable, EventTask $task, User $actor): EventVendorDeliverable
    {
        if ((int) $task->event_id !== (int) $deliverable->event_id) {
            abort(404);
        }

        $deliverable->forceFill(['internal_task_id' => $task->id])->save();

        $this->audit->record(
            'vendor.deliverable.task_linked',
            "Deliverable ditautkan ke tugas internal: {$deliverable->title}",
            $deliverable->event,
            $actor,
            $deliverable,
            ['event_task_id' => $task->id],
        );

        return $deliverable->refresh();
    }

    protected function assertReviewerBelongs(EventVendorEngagement $engagement, mixed $membershipId): void
    {
        if ($membershipId === null) {
            return;
        }

        $exists = EventStaffMembership::query()
            ->where('event_id', $engagement->event_id)
            ->whereKey($membershipId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'reviewer_membership_id' => 'Peninjau tidak termasuk dalam tim acara ini.',
            ]);
        }
    }

    protected function assertContactBelongs(EventVendorEngagement $engagement, mixed $contactId): void
    {
        if ($contactId === null) {
            return;
        }

        $exists = VendorContact::query()
            ->where('vendor_id', $engagement->vendor_id)
            ->whereKey($contactId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'vendor_contact_id' => 'Kontak tidak termasuk vendor engagement ini.',
            ]);
        }
    }
}
