<?php

namespace App\Http\Controllers\VendorPortal;

use App\Enums\VendorArrivalStatus;
use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorCommunicationDirection;
use App\Enums\VendorCommunicationType;
use App\Enums\VendorCommunicationVisibility;
use App\Enums\VendorDeliverableStatus;
use App\Enums\VendorDocumentType;
use App\Enums\VendorDocumentVisibility;
use App\Enums\VendorLogisticsStatus;
use App\Enums\VendorSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorCommunication;
use App\Models\EventVendorDeliverable;
use App\Models\EventVendorDeliverableSubmission;
use App\Models\EventVendorDocument;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorScheduleReference;
use App\Services\AuditService;
use App\Services\VendorDocumentService;
use App\Services\VendorPortalAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * External vendor portal. Every action re-validates engagement-scoped access;
 * internal-only records (notes, commercial data, issues, reviews, competing
 * proposals) are never returned from these endpoints.
 */
class VendorPortalController extends Controller
{
    public function __construct(
        private readonly VendorPortalAccessService $access,
        private readonly VendorDocumentService $documents,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->access->expireStaleInvitations();

        $engagements = $this->access->accessibleEngagements($user)
            ->with(['vendor', 'event'])
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('vendor-portal.index', [
            'memberships' => $this->access->membershipsFor($user),
            'engagements' => $engagements,
        ]);
    }

    public function show(Request $request, EventVendorEngagement $engagement)
    {
        $membership = $this->access->authorizeEngagement($request->user(), $engagement);

        $engagement->load([
            'vendor',
            'event',
            'coordinator.user',
            'vendorContact',
            'deliverables' => fn ($query) => $query->orderBy('due_at'),
            'deliverables.submissions',
            'scheduleReferences.runOfShowItem',
            'scheduleReferences.station',
            'logisticsRequirements',
            'changeRequests',
            'documents' => fn ($query) => $query->whereIn('visibility', [
                VendorDocumentVisibility::VendorVisible->value,
                VendorDocumentVisibility::Shared->value,
            ])->latest(),
        ]);

        $communications = EventVendorCommunication::query()
            ->where('event_vendor_engagement_id', $engagement->id)
            ->where('visibility', VendorCommunicationVisibility::VendorVisible)
            ->with(['authorUser', 'authorContact'])
            ->latest()
            ->limit(50)
            ->get();

        return view('vendor-portal.show', [
            'membership' => $membership,
            'engagement' => $engagement,
            'documents' => $engagement->documents,
            'communications' => $communications,
        ]);
    }

    public function download(Request $request, EventVendorDocument $document)
    {
        $engagement = $document->engagement;

        abort_if($engagement === null, 404);

        $this->access->authorizeEngagement($request->user(), $engagement);

        abort_unless(in_array($document->visibility, [
            VendorDocumentVisibility::VendorVisible,
            VendorDocumentVisibility::Shared,
        ], true), 404);

        $this->audit->record(
            'vendor.portal.document.download',
            "Vendor mengunduh dokumen: {$document->title}",
            $engagement->event,
            $request->user(),
            $document,
        );

        return $this->documents->stream($document);
    }

    public function submitDeliverable(
        Request $request,
        EventVendorEngagement $engagement,
        EventVendorDeliverable $deliverable,
    ) {
        $membership = $this->access->authorizeEngagement($request->user(), $engagement);

        abort_unless((int) $deliverable->event_vendor_engagement_id === (int) $engagement->id, 404);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:4000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        if (! $deliverable->status->canTransitionTo(VendorDeliverableStatus::Submitted)) {
            throw ValidationException::withMessages([
                'deliverable' => 'Deliverable ini tidak dapat diserahkan pada status saat ini.',
            ]);
        }

        $document = null;

        if ($request->hasFile('file')) {
            $document = $this->documents->store(
                $engagement->event,
                $engagement,
                $request->file('file'),
                [
                    'type' => VendorDocumentType::Supporting->value,
                    'title' => 'Bukti deliverable: '.$deliverable->title,
                    'visibility' => VendorDocumentVisibility::VendorVisible->value,
                    'notes' => $data['notes'] ?? null,
                ],
                $request->user(),
            );
        }

        $submission = EventVendorDeliverableSubmission::create([
            'event_vendor_deliverable_id' => $deliverable->id,
            'submitted_by_user_id' => $request->user()->id,
            'submitted_by_contact_id' => $membership->vendor_contact_id,
            'notes' => $data['notes'] ?? null,
            'event_vendor_document_id' => $document?->id,
            'status' => VendorSubmissionStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        $deliverable->forceFill(['status' => VendorDeliverableStatus::Submitted])->save();

        $this->audit->record(
            'vendor.portal.deliverable.submit',
            "Vendor menyerahkan deliverable: {$deliverable->title}",
            $engagement->event,
            $request->user(),
            $submission,
        );

        return back()->with('status', 'Deliverable berhasil diserahkan.');
    }

    public function storeCommunication(Request $request, EventVendorEngagement $engagement)
    {
        $membership = $this->access->authorizeEngagement($request->user(), $engagement);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'type' => ['required', Rule::in([
                VendorCommunicationType::Clarification->value,
                VendorCommunicationType::ServiceConfirmation->value,
                VendorCommunicationType::ScheduleConfirmation->value,
                VendorCommunicationType::DeliverableUpdate->value,
                VendorCommunicationType::LogisticsUpdate->value,
                VendorCommunicationType::ChangeDiscussion->value,
            ])],
            'event_vendor_deliverable_id' => ['nullable', 'integer'],
        ]);

        $deliverableId = null;

        if (! empty($data['event_vendor_deliverable_id'])) {
            $deliverableId = EventVendorDeliverable::query()
                ->where('event_vendor_engagement_id', $engagement->id)
                ->whereKey($data['event_vendor_deliverable_id'])
                ->value('id');

            abort_if($deliverableId === null, 404);
        }

        $communication = EventVendorCommunication::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'direction' => VendorCommunicationDirection::Inbound->value,
            'visibility' => VendorCommunicationVisibility::VendorVisible->value,
            'type' => $data['type'],
            'author_user_id' => $request->user()->id,
            'author_contact_id' => $membership->vendor_contact_id,
            'body' => $data['body'],
            'event_vendor_deliverable_id' => $deliverableId,
        ]);

        $this->audit->record(
            'vendor.portal.communication',
            'Vendor mengirim komunikasi portal.',
            $engagement->event,
            $request->user(),
            $communication,
        );

        return back()->with('status', 'Pesan terkirim.');
    }

    public function storeChangeRequest(Request $request, EventVendorEngagement $engagement)
    {
        $membership = $this->access->authorizeEngagement($request->user(), $engagement);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'impact' => ['nullable', 'string', 'max:2000'],
            'proposed_changes' => ['nullable', 'array'],
            'proposed_changes.amount' => ['nullable', 'numeric', 'min:0'],
            'proposed_changes.scope' => ['nullable', 'string', 'max:2000'],
            'proposed_changes.schedule' => ['nullable', 'string', 'max:2000'],
        ]);

        $changeRequest = EventVendorChangeRequest::create([
            'organization_id' => $engagement->organization_id,
            'event_id' => $engagement->event_id,
            'event_vendor_engagement_id' => $engagement->id,
            'requested_by_user_id' => $request->user()->id,
            'requested_by_contact_id' => $membership->vendor_contact_id,
            'title' => $data['title'],
            'description' => $data['description'],
            'impact' => $data['impact'] ?? null,
            'proposed_changes' => $data['proposed_changes'] ?? null,
            'status' => VendorChangeRequestStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        $this->audit->record(
            'vendor.portal.change_request',
            "Vendor mengajukan perubahan: {$changeRequest->title}",
            $engagement->event,
            $request->user(),
            $changeRequest,
        );

        return back()->with('status', 'Permintaan perubahan diajukan untuk ditinjau.');
    }

    public function confirmSchedule(Request $request, EventVendorScheduleReference $reference)
    {
        $engagement = $reference->engagement;

        abort_if($engagement === null, 404);

        $this->access->authorizeEngagement($request->user(), $engagement);

        if (! $reference->arrival_status->canTransitionTo(VendorArrivalStatus::Confirmed)) {
            throw ValidationException::withMessages([
                'schedule' => 'Jadwal ini tidak dapat dikonfirmasi pada status saat ini.',
            ]);
        }

        $reference->forceFill([
            'arrival_status' => VendorArrivalStatus::Confirmed,
            'updated_by' => $request->user()->id,
        ])->save();

        $this->audit->record(
            'vendor.portal.schedule.confirm',
            'Vendor mengonfirmasi jadwal kedatangan.',
            $engagement->event,
            $request->user(),
            $reference,
        );

        return back()->with('status', 'Jadwal dikonfirmasi.');
    }

    public function confirmLogistics(Request $request, EventVendorLogisticsRequirement $requirement)
    {
        $engagement = $requirement->engagement;

        abort_if($engagement === null, 404);

        $this->access->authorizeEngagement($request->user(), $engagement);

        if (! $requirement->status->canTransitionTo(VendorLogisticsStatus::Acknowledged)) {
            throw ValidationException::withMessages([
                'logistics' => 'Kebutuhan logistik ini tidak dapat dikonfirmasi pada status saat ini.',
            ]);
        }

        $requirement->forceFill(['status' => VendorLogisticsStatus::Acknowledged])->save();

        $this->audit->record(
            'vendor.portal.logistics.confirm',
            'Vendor mengonfirmasi kebutuhan logistik.',
            $engagement->event,
            $request->user(),
            $requirement,
        );

        return back()->with('status', 'Kebutuhan logistik dikonfirmasi.');
    }
}
