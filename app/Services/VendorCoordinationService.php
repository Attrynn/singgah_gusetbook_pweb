<?php

namespace App\Services;

use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorCommunicationDirection;
use App\Enums\VendorCommunicationVisibility;
use App\Enums\VendorEngagementStatus;
use App\Enums\VendorIssueStatus;
use App\Models\EventIncident;
use App\Models\EventStaffMembership;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorCommunication;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorIssue;
use App\Models\EventVendorPerformanceReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Day-to-day vendor coordination: communications, issues, change requests and
 * performance reviews.
 *
 * Commercial terms only change through an approved change request. Approval
 * applies the requested amount/scope/schedule to the engagement and records a
 * new immutable revision; earlier revisions are never rewritten or deleted.
 */
class VendorCoordinationService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly VendorEngagementService $engagements,
    ) {}

    public function addCommunication(EventVendorEngagement $engagement, array $data, User $actor): EventVendorCommunication
    {
        if (! empty($data['event_vendor_deliverable_id'])) {
            $belongs = $engagement->deliverables()
                ->whereKey($data['event_vendor_deliverable_id'])
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'event_vendor_deliverable_id' => 'Deliverable tidak termasuk dalam engagement ini.',
                ]);
            }
        }

        if (! empty($data['event_vendor_change_request_id'])) {
            $belongs = $engagement->changeRequests()
                ->whereKey($data['event_vendor_change_request_id'])
                ->exists();

            if (! $belongs) {
                throw ValidationException::withMessages([
                    'event_vendor_change_request_id' => 'Permintaan perubahan tidak termasuk dalam engagement ini.',
                ]);
            }
        }

        $communication = EventVendorCommunication::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'direction' => VendorCommunicationDirection::Internal->value,
            'visibility' => $data['visibility'] ?? VendorCommunicationVisibility::InternalOnly->value,
            'type' => $data['type'],
            'author_user_id' => $actor->id,
            'author_contact_id' => null,
            'body' => $data['body'],
            'event_vendor_deliverable_id' => $data['event_vendor_deliverable_id'] ?? null,
            'event_vendor_change_request_id' => $data['event_vendor_change_request_id'] ?? null,
        ]);

        $this->audit->record(
            'vendor.communication.created',
            'Komunikasi vendor dicatat.',
            $engagement->event,
            $actor,
            $communication,
            ['visibility' => $communication->visibility->value, 'type' => $communication->type->value],
        );

        return $communication;
    }

    public function storeIssue(EventVendorEngagement $engagement, array $data, User $actor): EventVendorIssue
    {
        if (! empty($data['assigned_to'])) {
            $this->assertActiveEventStaff((int) $engagement->event_id, (int) $data['assigned_to']);
        }

        $issue = EventVendorIssue::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'],
            'status' => VendorIssueStatus::Open->value,
            'reported_by' => $actor->id,
            'assigned_to' => $data['assigned_to'] ?? null,
        ]);

        $this->audit->record(
            'vendor.issue.created',
            "Masalah vendor dicatat: {$issue->title}",
            $engagement->event,
            $actor,
            $issue,
            ['severity' => $issue->severity->value],
        );

        return $issue;
    }

    public function updateIssue(EventVendorIssue $issue, array $data, User $actor): EventVendorIssue
    {
        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== null) {
            $this->assertActiveEventStaff((int) $issue->event_id, (int) $data['assigned_to']);
        }

        $updates = array_intersect_key($data, array_flip([
            'title', 'description', 'severity', 'assigned_to',
        ]));

        if ($updates !== []) {
            $issue->forceFill($updates)->save();
        }

        $this->audit->record(
            'vendor.issue.updated',
            "Masalah vendor diperbarui: {$issue->title}",
            $issue->event,
            $actor,
            $issue,
        );

        return $issue->refresh();
    }

    public function transitionIssue(
        EventVendorIssue $issue,
        VendorIssueStatus $target,
        User $actor,
        ?string $resolutionNotes = null,
    ): EventVendorIssue {
        if (! $issue->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status masalah vendor tidak valid.',
            ]);
        }

        $attributes = ['status' => $target->value];

        if (in_array($target, [VendorIssueStatus::Resolved, VendorIssueStatus::Closed], true)) {
            if (trim((string) $resolutionNotes) === '') {
                throw ValidationException::withMessages([
                    'resolution_notes' => 'Catatan penyelesaian wajib diisi saat masalah diselesaikan atau ditutup.',
                ]);
            }

            $attributes['resolution_notes'] = $resolutionNotes;
            $attributes['resolved_by'] = $actor->id;
            $attributes['resolved_at'] = now();
        } else {
            $attributes['resolved_by'] = null;
            $attributes['resolved_at'] = null;
        }

        $issue->forceFill($attributes)->save();

        $this->audit->record(
            'vendor.issue.transitioned',
            "Status masalah vendor: {$issue->title} → {$target->value}",
            $issue->event,
            $actor,
            $issue,
        );

        return $issue->refresh();
    }

    public function linkIncident(EventVendorIssue $issue, EventIncident $incident, User $actor): EventVendorIssue
    {
        if ((int) $incident->event_id !== (int) $issue->event_id) {
            throw ValidationException::withMessages([
                'event_incident_id' => 'Insiden tidak termasuk dalam acara ini.',
            ]);
        }

        if ($issue->event_incident_id !== null && (int) $issue->event_incident_id !== (int) $incident->id) {
            throw ValidationException::withMessages([
                'event_incident_id' => 'Masalah ini sudah tertaut ke insiden lain.',
            ]);
        }

        // Only reference the existing incident record; vendor issues never create
        // a parallel incident stream.
        $issue->forceFill(['event_incident_id' => $incident->id])->save();

        $this->audit->record(
            'vendor.issue.incident_linked',
            "Masalah vendor ditautkan ke insiden: {$issue->title}",
            $issue->event,
            $actor,
            $issue,
            ['event_incident_id' => $incident->id],
        );

        return $issue->refresh();
    }

    public function createChangeRequest(EventVendorEngagement $engagement, array $data, User $actor): EventVendorChangeRequest
    {
        $status = VendorChangeRequestStatus::from($data['status'] ?? VendorChangeRequestStatus::Draft->value);

        if (! in_array($status, [VendorChangeRequestStatus::Draft, VendorChangeRequestStatus::Submitted], true)) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan perubahan baru hanya dapat berstatus draf atau diajukan.',
            ]);
        }

        $changeRequest = EventVendorChangeRequest::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'requested_by_user_id' => $actor->id,
            'requested_by_contact_id' => null,
            'title' => $data['title'],
            'description' => $data['description'],
            'proposed_changes' => $data['proposed_changes'] ?? null,
            'impact' => $data['impact'] ?? null,
            'status' => $status->value,
            'submitted_at' => $status === VendorChangeRequestStatus::Submitted ? now() : null,
        ]);

        $this->audit->record(
            'vendor.change_request.created',
            "Permintaan perubahan dibuat: {$changeRequest->title}",
            $engagement->event,
            $actor,
            $changeRequest,
            ['status' => $status->value],
        );

        return $changeRequest;
    }

    public function submitChangeRequest(EventVendorChangeRequest $changeRequest, User $actor): EventVendorChangeRequest
    {
        if ($changeRequest->status !== VendorChangeRequestStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya permintaan perubahan berstatus draf yang dapat diajukan.',
            ]);
        }

        $changeRequest->forceFill([
            'status' => VendorChangeRequestStatus::Submitted->value,
            'submitted_at' => now(),
        ])->save();

        $this->audit->record(
            'vendor.change_request.submitted',
            "Permintaan perubahan diajukan: {$changeRequest->title}",
            $changeRequest->event,
            $actor,
            $changeRequest,
        );

        return $changeRequest->refresh();
    }

    public function decideChangeRequest(
        EventVendorChangeRequest $changeRequest,
        VendorChangeRequestStatus $target,
        User $actor,
        ?string $notes = null,
    ): EventVendorChangeRequest {
        if (! in_array($changeRequest->status, [
            VendorChangeRequestStatus::Submitted,
            VendorChangeRequestStatus::UnderReview,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Permintaan perubahan ini tidak sedang menunggu keputusan.',
            ]);
        }

        if (! in_array($target, [
            VendorChangeRequestStatus::Approved,
            VendorChangeRequestStatus::Rejected,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Keputusan permintaan perubahan harus disetujui atau ditolak.',
            ]);
        }

        if ($target === VendorChangeRequestStatus::Rejected && trim((string) $notes) === '') {
            throw ValidationException::withMessages([
                'decision_notes' => 'Catatan keputusan wajib diisi saat menolak permintaan perubahan.',
            ]);
        }

        return DB::transaction(function () use ($changeRequest, $target, $actor, $notes): EventVendorChangeRequest {
            if ($target === VendorChangeRequestStatus::Approved) {
                $this->applyApprovedChanges($changeRequest, $actor);
            }

            $changeRequest->forceFill([
                'status' => $target->value,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ])->save();

            $this->audit->recordCritical(
                'vendor.change_request.decided',
                "Keputusan permintaan perubahan: {$changeRequest->title} → {$target->value}",
                $changeRequest->event,
                $actor,
                $changeRequest,
                ['decision_notes' => $notes],
            );

            return $changeRequest->refresh();
        });
    }

    public function withdrawChangeRequest(EventVendorChangeRequest $changeRequest, User $actor): EventVendorChangeRequest
    {
        if (! in_array($changeRequest->status, [
            VendorChangeRequestStatus::Submitted,
            VendorChangeRequestStatus::UnderReview,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya permintaan perubahan yang diajukan atau sedang ditinjau yang dapat ditarik.',
            ]);
        }

        $changeRequest->forceFill([
            'status' => VendorChangeRequestStatus::Withdrawn->value,
            'withdrawn_at' => now(),
        ])->save();

        $this->audit->record(
            'vendor.change_request.withdrawn',
            "Permintaan perubahan ditarik: {$changeRequest->title}",
            $changeRequest->event,
            $actor,
            $changeRequest,
        );

        return $changeRequest->refresh();
    }

    public function storePerformance(EventVendorEngagement $engagement, array $data, User $actor): EventVendorPerformanceReview
    {
        if (! in_array($engagement->status, [
            VendorEngagementStatus::Confirmed,
            VendorEngagementStatus::InPreparation,
            VendorEngagementStatus::InProgress,
            VendorEngagementStatus::Completed,
        ], true)) {
            throw ValidationException::withMessages([
                'engagement' => 'Penilaian kinerja hanya dapat diberikan untuk engagement yang telah dikonfirmasi.',
            ]);
        }

        $rating = (int) $data['overall_rating'];

        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'overall_rating' => 'Nilai kinerja harus berada di antara 1 dan 5.',
            ]);
        }

        $review = EventVendorPerformanceReview::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'vendor_id' => $engagement->vendor_id,
            'reviewer_user_id' => $actor->id,
            'overall_rating' => $rating,
            'criteria' => $data['criteria'] ?? null,
            'comments' => $data['comments'] ?? null,
            'reviewed_at' => now(),
        ]);

        $this->audit->record(
            'vendor.performance.reviewed',
            "Penilaian kinerja vendor dicatat: {$rating}/5",
            $engagement->event,
            $actor,
            $review,
            ['overall_rating' => $rating],
        );

        return $review;
    }

    /**
     * Apply an approved change request to the engagement and snapshot a revision.
     */
    protected function applyApprovedChanges(EventVendorChangeRequest $changeRequest, User $actor): void
    {
        $engagement = $changeRequest->engagement;

        if ($engagement === null) {
            throw ValidationException::withMessages([
                'engagement' => 'Engagement untuk permintaan perubahan ini tidak ditemukan.',
            ]);
        }

        $changes = $changeRequest->proposed_changes ?? [];
        $attributes = [];
        $scope = $engagement->scope_snapshot ?? [];

        if (array_key_exists('amount', $changes)) {
            $attributes['agreed_amount'] = $changes['amount'];
        }

        if (array_key_exists('scope', $changes)) {
            $scope['description'] = $changes['scope'];
            $attributes['scope_snapshot'] = $scope;
        }

        if (array_key_exists('schedule', $changes)) {
            $scope['execution_schedule'] = $changes['schedule'];
            $attributes['scope_snapshot'] = $scope;
        }

        if ($attributes !== []) {
            $engagement->forceFill($attributes)->save();
        }

        $this->engagements->snapshotRevision(
            $engagement->refresh(),
            $changeRequest,
            $actor,
            'Perubahan disetujui: '.$changeRequest->title,
        );
    }

    protected function assertActiveEventStaff(int $eventId, int $userId): void
    {
        $exists = EventStaffMembership::query()
            ->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Penanggung jawab harus staf aktif pada acara ini.',
            ]);
        }
    }
}
