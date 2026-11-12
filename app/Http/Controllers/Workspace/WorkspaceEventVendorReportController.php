<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\Organization;
use App\Services\VendorReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceEventVendorReportController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests, ExportsCsv;

    public function __construct(private readonly VendorReportService $reports) {}

    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewVendorReports', $event);

        $types = VendorReportService::types();

        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys($types))],
            'include_commercial' => ['sometimes', 'boolean'],
        ]);

        $type = $data['type'] ?? 'engagements';

        $includeCommercial = $request->boolean('include_commercial')
            && $request->user()->can('viewVendorCommercial', $event);

        return view('workspace.events.vendors.reports', [
            'organization' => $organization,
            'event' => $event,
            'report' => $this->reports->report($event, $type, $includeCommercial),
            'types' => $types,
            'includeCommercial' => $includeCommercial,
        ]);
    }

    public function export(Request $request, Organization $organization, Event $event): StreamedResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('exportVendorReports', $event);

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(VendorReportService::types()))],
            'include_commercial' => ['sometimes', 'boolean'],
        ]);

        $includeCommercial = $request->boolean('include_commercial')
            && $request->user()->can('viewVendorCommercial', $event);

        return $this->csvResponse(
            $this->reports->exportRows($event, $data['type'], $includeCommercial),
            'laporan-vendor-'.$data['type'].'-'.$event->id.'.csv',
        );
    }
}
