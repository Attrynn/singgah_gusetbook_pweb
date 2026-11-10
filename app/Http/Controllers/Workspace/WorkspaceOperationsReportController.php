<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\Organization;
use App\Services\OperationsReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceOperationsReportController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests, ExportsCsv;

    public function __construct(private readonly OperationsReportService $reports) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewReports', $event);

        return view('workspace.events.operations.reports', [
            'organization' => $organization,
            'event' => $event,
            'reports' => $this->reports->all($event, request()->user()),
            'canExport' => request()->user()->can('exportReports', $event),
        ]);
    }

    public function export(Request $request, Organization $organization, Event $event): StreamedResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('exportReports', $event);

        $data = $request->validate([
            'type' => [
                'required',
                Rule::in(['staff', 'stations', 'run_sheet', 'tasks', 'incidents', 'attendance']),
            ],
            'include_contact' => ['sometimes', 'boolean'],
        ]);

        $report = $this->reports->all($event, $request->user(), $request->boolean('include_contact'))[$data['type']];

        $headers = $report['headers'];
        $rows = $report['rows'];

        return $this->csvResponse(
            function (callable $write) use ($headers, $rows): void {
                $write($headers);

                foreach ($rows as $row) {
                    $write($row);
                }
            },
            $report['filename'],
        );
    }
}
