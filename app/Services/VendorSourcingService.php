<?php

namespace App\Services;

use App\Enums\VendorCandidateState;
use App\Enums\VendorProposalStatus;
use App\Enums\VendorRequirementStatus;
use App\Models\Event;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorProposal;
use App\Models\EventVendorRequirement;
use App\Models\EventVendorRequirementCandidate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Event vendor sourcing: requirements, shortlist candidates and proposals.
 *
 * Selection is explicit and auditable. Unselected proposals are preserved for
 * history; they are never deleted because another vendor won.
 */
class VendorSourcingService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly VendorEngagementService $engagements,
    ) {}

    public function createRequirement(Event $event, array $data, User $actor): EventVendorRequirement
    {
        if (! empty($data['vendor_category_id'])) {
            VendorCategory::query()
                ->where('organization_id', $event->organization_id)
                ->findOrFail($data['vendor_category_id']);
        }

        $requirement = EventVendorRequirement::create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'vendor_category_id' => $data['vendor_category_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'scope' => $data['scope'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'needed_by' => $data['needed_by'] ?? null,
            'desired_availability' => $data['desired_availability'] ?? null,
            'owner_membership_id' => $data['owner_membership_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => VendorRequirementStatus::Open->value,
            'created_by' => $actor->id,
        ]);

        $this->audit->record(
            'vendor.requirement.created',
            "Kebutuhan vendor dibuat: {$requirement->title}",
            $event,
            $actor,
            $requirement,
        );

        return $requirement;
    }

    public function updateRequirement(Event $event, EventVendorRequirement $requirement, array $data, User $actor): EventVendorRequirement
    {
        $this->assertRequirementBelongs($event, $requirement);

        if (in_array($requirement->status, [VendorRequirementStatus::VendorSelected, VendorRequirementStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'requirement' => 'Kebutuhan yang sudah selesai tidak dapat diubah.',
            ]);
        }

        $requirement->forceFill(array_intersect_key($data, array_flip([
            'vendor_category_id', 'title', 'description', 'scope', 'quantity',
            'needed_by', 'desired_availability', 'owner_membership_id', 'notes', 'status',
        ])))->save();

        $this->audit->record(
            'vendor.requirement.updated',
            "Kebutuhan vendor diperbarui: {$requirement->title}",
            $event,
            $actor,
            $requirement,
        );

        return $requirement->refresh();
    }

    public function deleteRequirement(Event $event, EventVendorRequirement $requirement, User $actor): void
    {
        $this->assertRequirementBelongs($event, $requirement);

        if ($requirement->proposals()->exists()) {
            throw ValidationException::withMessages([
                'requirement' => 'Kebutuhan yang sudah memiliki proposal tidak dapat dihapus; batalkan saja.',
            ]);
        }

        $requirement->delete();

        $this->audit->record(
            'vendor.requirement.deleted',
            "Kebutuhan vendor dihapus: {$requirement->title}",
            $event,
            $actor,
        );
    }

    public function addCandidate(EventVendorRequirement $requirement, int $vendorId, User $actor, ?string $notes = null): EventVendorRequirementCandidate
    {
        $vendor = Vendor::query()
            ->where('organization_id', $requirement->organization_id)
            ->findOrFail($vendorId);

        if ($requirement->candidates()->where('vendor_id', $vendor->id)->exists()) {
            throw ValidationException::withMessages([
                'vendor_id' => 'Vendor ini sudah ada pada daftar kandidat.',
            ]);
        }

        $candidate = EventVendorRequirementCandidate::create([
            'event_vendor_requirement_id' => $requirement->id,
            'vendor_id' => $vendor->id,
            'state' => VendorCandidateState::Candidate->value,
            'notes' => $notes,
            'added_by' => $actor->id,
        ]);

        if ($requirement->status === VendorRequirementStatus::Open) {
            $requirement->forceFill(['status' => VendorRequirementStatus::Sourcing->value])->save();
        }

        $this->audit->record(
            'vendor.candidate.added',
            "Kandidat vendor ditambahkan: {$vendor->name}",
            $requirement->event,
            $actor,
            $candidate,
        );

        return $candidate;
    }

    public function removeCandidate(EventVendorRequirement $requirement, EventVendorRequirementCandidate $candidate, User $actor): void
    {
        if ((int) $candidate->event_vendor_requirement_id !== (int) $requirement->id) {
            abort(404);
        }

        if ($requirement->proposals()->where('vendor_id', $candidate->vendor_id)->exists()) {
            throw ValidationException::withMessages([
                'candidate' => 'Kandidat yang sudah mengirim proposal tidak dapat dihapus.',
            ]);
        }

        $candidate->delete();

        $this->audit->record(
            'vendor.candidate.removed',
            'Kandidat vendor dihapus dari daftar pendek.',
            $requirement->event,
            $actor,
        );
    }

    public function preferCandidate(EventVendorRequirement $requirement, EventVendorRequirementCandidate $candidate, User $actor): EventVendorRequirementCandidate
    {
        if ((int) $candidate->event_vendor_requirement_id !== (int) $requirement->id) {
            abort(404);
        }

        DB::transaction(function () use ($requirement, $candidate): void {
            $requirement->candidates()->update(['is_preferred' => false]);
            $candidate->forceFill([
                'is_preferred' => true,
                'state' => VendorCandidateState::Preferred->value,
            ])->save();
        });

        $this->audit->record(
            'vendor.candidate.preferred',
            'Kandidat vendor ditandai sebagai pilihan utama.',
            $requirement->event,
            $actor,
            $candidate,
        );

        return $candidate->refresh();
    }

    public function storeProposal(EventVendorRequirement $requirement, array $data, User $actor): EventVendorProposal
    {
        $vendor = Vendor::query()
            ->where('organization_id', $requirement->organization_id)
            ->findOrFail($data['vendor_id']);

        $status = VendorProposalStatus::from($data['status'] ?? VendorProposalStatus::Draft->value);

        $proposal = EventVendorProposal::create([
            'organization_id' => $requirement->organization_id,
            'event_id' => $requirement->event_id,
            'event_vendor_requirement_id' => $requirement->id,
            'vendor_id' => $vendor->id,
            'vendor_service_package_id' => $data['vendor_service_package_id'] ?? null,
            'title' => $data['title'],
            'service_description' => $data['service_description'] ?? null,
            'quoted_amount' => $data['quoted_amount'] ?? null,
            'currency' => $data['currency'] ?? 'IDR',
            'inclusions' => $data['inclusions'] ?? null,
            'exclusions' => $data['exclusions'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'proposed_schedule' => $data['proposed_schedule'] ?? null,
            'status' => $status->value,
            'evaluation_notes' => $data['evaluation_notes'] ?? null,
            'evaluation_rating' => $data['evaluation_rating'] ?? null,
            'submitted_at' => $status === VendorProposalStatus::Submitted ? now() : null,
            'created_by' => $actor->id,
        ]);

        if (in_array($requirement->status, [VendorRequirementStatus::Open, VendorRequirementStatus::Sourcing], true)) {
            $requirement->forceFill(['status' => VendorRequirementStatus::ProposalsReceived->value])->save();
        }

        $this->audit->record(
            'vendor.proposal.created',
            "Proposal vendor dicatat: {$proposal->title}",
            $requirement->event,
            $actor,
            $proposal,
            ['quoted_amount' => $proposal->quoted_amount, 'currency' => $proposal->currency],
        );

        return $proposal;
    }

    public function updateProposal(Event $event, EventVendorProposal $proposal, array $data, User $actor): EventVendorProposal
    {
        $this->assertProposalBelongs($event, $proposal);

        if (! in_array($proposal->status, [
            VendorProposalStatus::Draft,
            VendorProposalStatus::Submitted,
            VendorProposalStatus::UnderEvaluation,
        ], true)) {
            throw ValidationException::withMessages([
                'proposal' => 'Proposal pada status ini tidak dapat diubah.',
            ]);
        }

        $proposal->forceFill(array_intersect_key($data, array_flip([
            'title', 'service_description', 'quoted_amount', 'currency', 'inclusions',
            'exclusions', 'valid_until', 'proposed_schedule', 'evaluation_notes', 'evaluation_rating',
        ])))->save();

        $this->audit->record(
            'vendor.proposal.updated',
            "Proposal vendor diperbarui: {$proposal->title}",
            $event,
            $actor,
            $proposal,
        );

        return $proposal->refresh();
    }

    public function transitionProposal(
        Event $event,
        EventVendorProposal $proposal,
        VendorProposalStatus $target,
        User $actor,
    ): EventVendorProposal {
        $this->assertProposalBelongs($event, $proposal);

        if (! $proposal->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status proposal tidak valid.',
            ]);
        }

        if ($target === VendorProposalStatus::Submitted) {
            $proposal->submitted_at = now();
        }

        $proposal->status = $target;
        $proposal->save();

        $this->audit->record(
            'vendor.proposal.transitioned',
            "Status proposal vendor: {$proposal->title} → {$target->value}",
            $event,
            $actor,
            $proposal,
        );

        return $proposal->refresh();
    }

    /**
     * Select a proposal and move the vendor into the engagement process.
     * Other proposals on the same requirement are rejected but preserved.
     */
    public function selectProposal(
        Event $event,
        EventVendorProposal $proposal,
        string $reason,
        User $actor,
    ): EventVendorEngagement {
        $this->assertProposalBelongs($event, $proposal);

        if ($proposal->status === VendorProposalStatus::Expired || $proposal->isExpired()) {
            throw ValidationException::withMessages([
                'proposal' => 'Proposal yang sudah kedaluwarsa tidak dapat dipilih.',
            ]);
        }

        if (! in_array($proposal->status, [
            VendorProposalStatus::Submitted,
            VendorProposalStatus::UnderEvaluation,
        ], true)) {
            throw ValidationException::withMessages([
                'proposal' => 'Hanya proposal yang diajukan atau sedang dievaluasi yang dapat dipilih.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'selection_reason' => 'Alasan pemilihan vendor wajib diisi.',
            ]);
        }

        $engagement = DB::transaction(function () use ($event, $proposal, $reason, $actor) {
            $proposal->forceFill([
                'status' => VendorProposalStatus::Selected->value,
            ])->save();

            EventVendorProposal::query()
                ->where('event_vendor_requirement_id', $proposal->event_vendor_requirement_id)
                ->whereKeyNot($proposal->getKey())
                ->whereIn('status', [
                    VendorProposalStatus::Submitted->value,
                    VendorProposalStatus::UnderEvaluation->value,
                ])
                ->update(['status' => VendorProposalStatus::Rejected->value]);

            $requirement = $proposal->requirement;
            $requirement?->forceFill(['status' => VendorRequirementStatus::VendorSelected->value])->save();

            $requirement?->candidates()->update(['state' => VendorCandidateState::Rejected->value]);
            $requirement?->candidates()
                ->where('vendor_id', $proposal->vendor_id)
                ->update(['state' => VendorCandidateState::Selected->value]);

            return $this->engagements->create($event, [
                'vendor_id' => $proposal->vendor_id,
                'event_vendor_requirement_id' => $proposal->event_vendor_requirement_id,
                'event_vendor_proposal_id' => $proposal->id,
                'vendor_service_package_id' => $proposal->vendor_service_package_id,
                'title' => $proposal->title,
                'agreed_amount' => $proposal->quoted_amount,
                'currency' => $proposal->currency,
                'selection_reason' => $reason,
                'scope_description' => $proposal->service_description,
                'inclusions' => $proposal->inclusions,
                'exclusions' => $proposal->exclusions,
            ], $actor);
        });

        $this->audit->recordCritical(
            'vendor.proposal.selected',
            "Vendor dipilih untuk kebutuhan: {$proposal->title}",
            $event,
            $actor,
            $proposal,
            ['reason' => $reason, 'engagement_id' => $engagement->id],
        );

        return $engagement;
    }

    /**
     * @return array<int, EventVendorProposal>
     */
    public function comparison(EventVendorRequirement $requirement): array
    {
        return $requirement->proposals()
            ->with(['vendor', 'package'])
            ->orderByRaw('quoted_amount is null')
            ->orderBy('quoted_amount')
            ->get()
            ->all();
    }

    protected function assertRequirementBelongs(Event $event, EventVendorRequirement $requirement): void
    {
        if ((int) $requirement->event_id !== (int) $event->id) {
            abort(404);
        }
    }

    protected function assertProposalBelongs(Event $event, EventVendorProposal $proposal): void
    {
        if ((int) $proposal->event_id !== (int) $event->id) {
            abort(404);
        }
    }
}
