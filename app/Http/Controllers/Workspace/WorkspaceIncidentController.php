<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\Organization;
use App\Models\User;
use App\Services\IncidentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceIncidentController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly IncidentService $incidents) {}

    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewIncidents', $event);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(IncidentStatus::class)],
            'severity' => ['nullable', Rule::enum(IncidentSeverity::class)],
        ]);

        $query = $this->incidents->visibleIncidents($event, $request->user())
            ->with(['reporter', 'assignee', 'station', 'updates.actor'])
            ->latest('reported_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        return view('workspace.events.operations.incidents', [
            'organization' => $organization,
            'event' => $event,
            'incidents' => $query->get(),
            'stations' => EventStation::query()->where('event_id', $event->id)->orderBy('name')->get(),
            'managers' => $event->staffMemberships()
                ->with('user')
                ->where('status', MembershipStatus::Active->value)
                ->get()
                ->filter(fn (EventStaffMembership $membership) => $membership->user !== null),
            'categories' => IncidentCategory::cases(),
            'severities' => IncidentSeverity::cases(),
            'statuses' => IncidentStatus::cases(),
            'filters' => $filters,
            'canManage' => $request->user()->can('manageIncidents', $event),
            'canReport' => $request->user()->can('reportIncidents', $event),
            'canResolve' => $request->user()->can('resolveIncidents', $event),
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('reportIncidents', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category' => ['nullable', Rule::enum(IncidentCategory::class)],
            'severity' => ['nullable', Rule::enum(IncidentSeverity::class)],
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->incidents->report($event, $data, $request->user());

        return back()->with('status', 'Insiden dilaporkan.');
    }

    public function update(Request $request, Organization $organization, Event $event, EventIncident $incident)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $incident->event_id === (int) $event->id, 404);
        $this->authorize('manageIncidents', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category' => ['nullable', Rule::enum(IncidentCategory::class)],
            'severity' => ['nullable', Rule::enum(IncidentSeverity::class)],
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
        ]);

        $this->incidents->update($event, $incident, $data, $request->user());

        return back()->with('status', 'Insiden diperbarui.');
    }

    public function assign(Request $request, Organization $organization, Event $event, EventIncident $incident)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $incident->event_id === (int) $event->id, 404);
        $this->authorize('manageIncidents', $event);

        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assignee = empty($data['assigned_to'])
            ? null
            : User::query()->findOrFail($data['assigned_to']);

        $this->incidents->assign($event, $incident, $assignee, $request->user());

        return back()->with('status', 'Penanggung jawab insiden diperbarui.');
    }

    public function status(Request $request, Organization $organization, Event $event, EventIncident $incident)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $incident->event_id === (int) $event->id, 404);
        $this->authorize('viewIncidents', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(IncidentStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->incidents->transition(
            $event,
            $incident,
            IncidentStatus::from($data['status']),
            $request->user(),
            $data['notes'] ?? null,
        );

        return back()->with('status', 'Status insiden diperbarui.');
    }

    public function escalate(Request $request, Organization $organization, Event $event, EventIncident $incident)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $incident->event_id === (int) $event->id, 404);
        $this->authorize('viewIncidents', $event);

        $data = $request->validate([
            'escalated_to' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:500'],
            'severity' => ['nullable', Rule::enum(IncidentSeverity::class)],
        ]);

        $target = empty($data['escalated_to'])
            ? null
            : User::query()->findOrFail($data['escalated_to']);

        $this->incidents->escalate(
            $event,
            $incident,
            $target,
            $data['reason'],
            $request->user(),
            isset($data['severity']) ? IncidentSeverity::from($data['severity']) : null,
        );

        return back()->with('status', 'Insiden dieskalasi.');
    }

    public function resolve(Request $request, Organization $organization, Event $event, EventIncident $incident)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $incident->event_id === (int) $event->id, 404);
        $this->authorize('resolveIncidents', $event);

        $data = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        $this->incidents->resolve($event, $incident, $data['resolution_notes'], $request->user());

        return back()->with('status', 'Insiden diselesaikan.');
    }
}
