<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorChangeRequestStatus;
use App\Enums\VendorCommunicationType;
use App\Enums\VendorCommunicationVisibility;
use App\Enums\VendorIssueSeverity;
use App\Enums\VendorIssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorIssue;
use App\Models\Organization;
use App\Services\VendorCoordinationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorCoordinationController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly VendorCoordinationService $coordination) {}

    public function storeCommunication(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorEngagement $engagement,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'visibility' => ['required', Rule::enum(VendorCommunicationVisibility::class)],
            'type' => ['required', Rule::enum(VendorCommunicationType::class)],
            'body' => ['required', 'string', 'max:5000'],
            'event_vendor_deliverable_id' => ['nullable', 'integer'],
            'event_vendor_change_request_id' => ['nullable', 'integer'],
        ], [
            'visibility.required' => 'Visibilitas komunikasi wajib dipilih.',
            'type.required' => 'Jenis komunikasi wajib dipilih.',
            'body.required' => 'Isi komunikasi wajib diisi.',
        ]);

        $this->coordination->addCommunication($engagement, $data, $request->user());

        return back()->with('status', 'Komunikasi vendor tersimpan.');
    }

    public function storeIssue(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorEngagement $engagement,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'severity' => ['required', Rule::enum(VendorIssueSeverity::class)],
            'assigned_to' => ['nullable', 'integer'],
        ], [
            'title.required' => 'Judul masalah wajib diisi.',
            'severity.required' => 'Tingkat keparahan wajib dipilih.',
        ]);

        $this->coordination->storeIssue($engagement, $data, $request->user());

        return back()->with('status', 'Masalah vendor dicatat.');
    }

    public function updateIssue(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorIssue $issue,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $issue->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'severity' => ['sometimes', Rule::enum(VendorIssueSeverity::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
        ], [
            'title.required' => 'Judul masalah wajib diisi.',
        ]);

        $this->coordination->updateIssue($issue, $data, $request->user());

        return back()->with('status', 'Masalah vendor diperbarui.');
    }

    public function issueStatus(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorIssue $issue,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $issue->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorIssueStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'status.required' => 'Status masalah wajib dipilih.',
        ]);

        $this->coordination->transitionIssue(
            $issue,
            VendorIssueStatus::from($data['status']),
            $request->user(),
            $data['resolution_notes'] ?? null,
        );

        return back()->with('status', 'Status masalah vendor diperbarui.');
    }

    public function linkIncident(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorIssue $issue,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $issue->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'event_incident_id' => ['required', 'integer'],
        ], [
            'event_incident_id.required' => 'Insiden wajib dipilih.',
        ]);

        $incident = EventIncident::query()
            ->where('event_id', $event->id)
            ->findOrFail($data['event_incident_id']);

        $this->coordination->linkIncident($issue, $incident, $request->user());

        return back()->with('status', 'Masalah vendor ditautkan ke insiden.');
    }

    public function storeChangeRequest(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorEngagement $engagement,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'proposed_changes' => ['nullable', 'array'],
            'proposed_changes.amount' => ['nullable', 'numeric', 'min:0'],
            'proposed_changes.scope' => ['nullable', 'string', 'max:2000'],
            'proposed_changes.schedule' => ['nullable', 'string', 'max:2000'],
            'impact' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in([
                VendorChangeRequestStatus::Draft->value,
                VendorChangeRequestStatus::Submitted->value,
            ])],
        ], [
            'title.required' => 'Judul permintaan perubahan wajib diisi.',
            'description.required' => 'Deskripsi permintaan perubahan wajib diisi.',
            'status.in' => 'Permintaan perubahan baru hanya dapat berstatus draf atau diajukan.',
        ]);

        $this->coordination->createChangeRequest($engagement, $data, $request->user());

        return back()->with('status', 'Permintaan perubahan tersimpan.');
    }

    public function submitChangeRequest(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorChangeRequest $changeRequest,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $changeRequest->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $this->coordination->submitChangeRequest($changeRequest, $request->user());

        return back()->with('status', 'Permintaan perubahan diajukan.');
    }

    public function decideChangeRequest(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorChangeRequest $changeRequest,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $changeRequest->event_id === (int) $event->id, 404);
        $this->authorize('approveVendorChanges', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorChangeRequestStatus::class)],
            'decision_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'status.required' => 'Keputusan permintaan perubahan wajib dipilih.',
        ]);

        $target = VendorChangeRequestStatus::from($data['status']);

        $requiresCommercialApproval = array_intersect(
            array_keys($changeRequest->proposed_changes ?? []),
            ['amount', 'scope', 'schedule'],
        ) !== [];

        if ($requiresCommercialApproval) {
            $this->authorize('approveVendorCommercial', $event);
        }

        $this->coordination->decideChangeRequest(
            $changeRequest,
            $target,
            $request->user(),
            $data['decision_notes'] ?? null,
        );

        return back()->with('status', 'Keputusan permintaan perubahan tersimpan.');
    }

    public function withdrawChangeRequest(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorChangeRequest $changeRequest,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $changeRequest->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $this->coordination->withdrawChangeRequest($changeRequest, $request->user());

        return back()->with('status', 'Permintaan perubahan ditarik.');
    }

    public function storePerformance(
        Request $request,
        Organization $organization,
        Event $event,
        EventVendorEngagement $engagement,
    ) {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('reviewVendorPerformance', $event);

        $data = $request->validate([
            'overall_rating' => ['required', 'integer', 'between:1,5'],
            'criteria' => ['nullable', 'array'],
            'comments' => ['nullable', 'string', 'max:5000'],
        ], [
            'overall_rating.required' => 'Nilai kinerja wajib diisi.',
            'overall_rating.between' => 'Nilai kinerja harus berada di antara 1 dan 5.',
        ]);

        $this->coordination->storePerformance($engagement, $data, $request->user());

        return back()->with('status', 'Penilaian kinerja vendor tersimpan.');
    }
}
