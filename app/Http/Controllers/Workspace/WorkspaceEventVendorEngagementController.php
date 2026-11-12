<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorEngagementStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventVendorEngagement;
use App\Models\Organization;
use App\Models\VendorContact;
use App\Services\AuditService;
use App\Services\VendorEngagementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceEventVendorEngagementController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(
        private readonly VendorEngagementService $engagements,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewVendorEngagements', $event);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(VendorEngagementStatus::class)],
        ]);

        $query = $event->vendorEngagements()
            ->with(['vendor', 'coordinator.user'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('workspace.events.vendors.engagements', [
            'organization' => $organization,
            'event' => $event,
            'engagements' => $query->paginate(20)->withQueryString(),
            'statuses' => VendorEngagementStatus::cases(),
            'filters' => $filters,
            'canManage' => $request->user()->can('manageVendorEngagements', $event),
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'vendor_id' => [
                'required', 'integer',
                Rule::exists('vendors', 'id')->where('organization_id', $event->organization_id),
            ],
            'title' => ['required', 'string', 'max:180'],
            'event_vendor_requirement_id' => [
                'nullable', 'integer',
                Rule::exists('event_vendor_requirements', 'id')->where('event_id', $event->id),
            ],
            'event_vendor_proposal_id' => [
                'nullable', 'integer',
                Rule::exists('event_vendor_proposals', 'id')->where('event_id', $event->id),
            ],
            'vendor_service_id' => ['nullable', 'integer', 'exists:vendor_services,id'],
            'vendor_service_package_id' => ['nullable', 'integer', 'exists:vendor_service_packages,id'],
            'coordinator_membership_id' => [
                'nullable', 'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
            'agreed_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'selection_reason' => ['nullable', 'string', 'max:2000'],
            'scope_description' => ['nullable', 'string', 'max:5000'],
            'inclusions' => ['nullable', 'array'],
            'exclusions' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $engagement = $this->engagements->create($event, $data, $request->user());

        return redirect()
            ->route('workspace.events.vendors.engagements.show', [$organization, $event, $engagement])
            ->with('status', 'Engagement vendor dibuat.');
    }

    public function show(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('viewVendorEngagements', $event);

        $engagement->load([
            'vendor',
            'coordinator.user',
            'vendorContact',
            'proposal',
            'service',
            'package',
            'documents.uploader',
            'deliverables.submissions',
            'scheduleReferences.runOfShowItem',
            'scheduleReferences.station',
            'logisticsRequirements.station',
            'changeRequests.requestedByUser',
            'communications.authorUser',
            'issues.assignee',
            'performanceReviews.reviewer',
            'revisions',
        ]);

        $user = $request->user();

        return view('workspace.events.vendors.engagement', [
            'organization' => $organization,
            'event' => $event,
            'engagement' => $engagement,
            'readiness' => $this->engagements->closureReadiness($engagement),
            'staff' => $event->staffMemberships()->with('user')->where('status', 'active')->get(),
            'vendorContacts' => $engagement->vendor->contacts()->where('is_active', true)->get(),
            'canManage' => $user->can('manageVendorEngagements', $event),
            'canClose' => $user->can('closeVendorEngagements', $event),
            'canReviewDeliverables' => $user->can('reviewVendorDeliverables', $event),
            'canManageLogistics' => $user->can('manageVendorLogistics', $event),
            'canViewCommercial' => $user->can('viewVendorCommercial', $event),
            'canManagePortal' => $user->can('manageVendorPortal', $event),
            'canViewPerformance' => $user->can('viewVendorPerformance', $event),
            'canManageAgreements' => $user->can('manageVendorAgreements', $event),
            'canApproveChanges' => $user->can('approveVendorChanges', $event),
        ]);
    }

    public function update(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:180'],
            'coordinator_membership_id' => [
                'nullable', 'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'agreed_amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'scope_snapshot' => ['sometimes', 'array'],
        ]);

        $this->assertContactBelongs($engagement, $data['vendor_contact_id'] ?? null);

        $this->engagements->update($event, $engagement, $data, $request->user());

        return back()->with('status', 'Engagement vendor diperbarui.');
    }

    public function status(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(VendorEngagementStatus::class)],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = VendorEngagementStatus::from($data['status']);

        if ($target === VendorEngagementStatus::Cancelled) {
            $this->authorize('manageVendorEngagements', $event);
            $this->engagements->cancel($event, $engagement, $data['reason'] ?? '', $request->user());

            return back()->with('status', 'Engagement vendor dibatalkan.');
        }

        $this->engagements->transition($event, $engagement, $target, $request->user(), $data);

        return back()->with('status', 'Status engagement vendor diperbarui.');
    }

    public function cancel(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->engagements->cancel($event, $engagement, $data['reason'], $request->user());

        return back()->with('status', 'Engagement vendor dibatalkan.');
    }

    public function close(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('closeVendorEngagements', $event);

        $data = $request->validate([
            'closure_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->engagements->close($event, $engagement, $data, $request->user());

        return back()->with('status', 'Engagement vendor ditutup.');
    }

    public function assign(Request $request, Organization $organization, Event $event, EventVendorEngagement $engagement)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $engagement->event_id === (int) $event->id, 404);
        $this->authorize('manageVendorEngagements', $event);

        $data = $request->validate([
            'coordinator_membership_id' => [
                'nullable', 'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'vendor_contact_id' => ['nullable', 'integer', 'exists:vendor_contacts,id'],
        ]);

        $this->assertContactBelongs($engagement, $data['vendor_contact_id'] ?? null);

        $engagement->forceFill([
            'coordinator_membership_id' => $data['coordinator_membership_id'] ?? null,
            'vendor_contact_id' => $data['vendor_contact_id'] ?? null,
        ])->save();

        $this->audit->record(
            'vendor.engagement.assigned',
            "Penugasan engagement vendor diperbarui: {$engagement->title}",
            $event,
            $request->user(),
            $engagement,
        );

        return back()->with('status', 'Penugasan engagement diperbarui.');
    }

    protected function assertContactBelongs(EventVendorEngagement $engagement, mixed $contactId): void
    {
        if ($contactId === null) {
            return;
        }

        $belongs = VendorContact::query()
            ->where('vendor_id', $engagement->vendor_id)
            ->whereKey($contactId)
            ->exists();

        if (! $belongs) {
            abort(404);
        }
    }
}
