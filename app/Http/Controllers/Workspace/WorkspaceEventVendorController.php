<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VendorDocumentReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventVendorChangeRequest;
use App\Models\EventVendorDeliverable;
use App\Models\EventVendorDocument;
use App\Models\EventVendorEngagement;
use App\Models\EventVendorIssue;
use App\Models\EventVendorLogisticsRequirement;
use App\Models\EventVendorProposal;
use App\Models\EventVendorRequirement;
use App\Models\EventVendorScheduleReference;
use App\Models\Organization;
use App\Services\VendorReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkspaceEventVendorController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly VendorReportService $reports) {}

    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewVendors', $event);

        $user = $request->user();
        $canManageCommercial = $user->can('viewVendorCommercial', $event);

        $requirements = EventVendorRequirement::query()
            ->where('event_id', $event->id)
            ->with(['candidates.vendor', 'proposals.vendor'])
            ->orderBy('title')
            ->get();

        $proposals = EventVendorProposal::query()
            ->where('event_id', $event->id)
            ->with('vendor')
            ->latest()
            ->get();

        $engagements = EventVendorEngagement::query()
            ->where('event_id', $event->id)
            ->with(['vendor', 'coordinator.user'])
            ->orderBy('title')
            ->get();

        $documents = EventVendorDocument::query()
            ->where('event_id', $event->id)
            ->where('review_status', VendorDocumentReviewStatus::UnderReview->value)
            ->with('engagement.vendor')
            ->latest()
            ->get();

        $deliverables = EventVendorDeliverable::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->orderBy('due_at')
            ->get();

        $scheduleReferences = EventVendorScheduleReference::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->orderBy('expected_arrival_at')
            ->get();

        $logisticsRequirements = EventVendorLogisticsRequirement::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->orderBy('due_at')
            ->get();

        $issues = EventVendorIssue::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->latest()
            ->get();

        $changeRequests = EventVendorChangeRequest::query()
            ->where('event_id', $event->id)
            ->with('engagement.vendor')
            ->latest()
            ->get();

        return view('workspace.events.vendors.index', [
            'organization' => $organization,
            'event' => $event,
            'data' => $this->reports->hub($event, $canManageCommercial),
            'canManageCommercial' => $canManageCommercial,
            'canApproveChanges' => $user->can('approveVendorChanges', $event),
            'canReviewDeliverables' => $user->can('reviewVendorDeliverables', $event),
            'canManageLogistics' => $user->can('manageVendorLogistics', $event),
            'canManagePortal' => $user->can('manageVendorPortal', $event),
            'canViewPerformance' => $user->can('viewVendorPerformance', $event),
            'requirements' => $requirements,
            'proposals' => $proposals,
            'engagements' => $engagements,
            'documents' => $documents,
            'deliverables' => $deliverables,
            'scheduleReferences' => $scheduleReferences,
            'logisticsRequirements' => $logisticsRequirements,
            'issues' => $issues,
            'changeRequests' => $changeRequests,
        ]);
    }
}
