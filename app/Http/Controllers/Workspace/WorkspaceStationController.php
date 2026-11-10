<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\MembershipStatus;
use App\Enums\StationCategory;
use App\Enums\StationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\Organization;
use App\Services\StationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceStationController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly StationService $stations) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewStations', $event);

        $stations = EventStation::query()
            ->where('event_id', $event->id)
            ->with(['supervisor.user', 'activeAssignments.membership.user'])
            ->orderBy('name')
            ->get();

        return view('workspace.events.operations.stations', [
            'organization' => $organization,
            'event' => $event,
            'stations' => $stations,
            'staff' => $event->staffMemberships()
                ->with('user')
                ->where('status', MembershipStatus::Active->value)
                ->get(),
            'categories' => StationCategory::cases(),
            'statuses' => StationStatus::cases(),
            'coverage' => $stations->mapWithKeys(
                fn (EventStation $station) => [$station->id => $this->stations->coverage($station)],
            ),
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageStations', $event);

        $data = $request->validate($this->rules($event));

        $this->stations->create($event, $data, $request->user());

        return back()->with('status', 'Stasiun ditambahkan.');
    }

    public function update(Request $request, Organization $organization, Event $event, EventStation $station)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $station->event_id === (int) $event->id, 404);
        $this->authorize('manageStations', $event);

        $data = $request->validate($this->rules($event, $station));

        $this->stations->update($event, $station, $data, $request->user());

        return back()->with('status', 'Stasiun diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventStation $station)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $station->event_id === (int) $event->id, 404);
        $this->authorize('manageStations', $event);

        $this->stations->delete($event, $station, request()->user());

        return back()->with('status', 'Stasiun dihapus.');
    }

    public function status(Request $request, Organization $organization, Event $event, EventStation $station)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $station->event_id === (int) $event->id, 404);
        $this->authorize('manageStations', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(StationStatus::class)],
        ]);

        $this->stations->transition($event, $station, StationStatus::from($data['status']), $request->user());

        return back()->with('status', 'Status stasiun diperbarui.');
    }

    public function assign(Request $request, Organization $organization, Event $event, EventStation $station)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $station->event_id === (int) $event->id, 404);
        $this->authorize('assignStations', $event);

        $data = $request->validate([
            'staff_membership_id' => [
                'required',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'is_supervisor' => ['sometimes', 'boolean'],
        ]);

        $membership = EventStaffMembership::query()
            ->whereKey($data['staff_membership_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $this->stations->assignStaff(
            $event,
            $station,
            $membership,
            $request->user(),
            $request->boolean('is_supervisor'),
        );

        return back()->with('status', 'Staf ditugaskan ke stasiun.');
    }

    public function unassign(Organization $organization, Event $event, EventStation $station, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $station->event_id === (int) $event->id, 404);
        $this->authorize('assignStations', $event);

        $this->stations->unassignStaff($event, $station, $membership, request()->user());

        return back()->with('status', 'Penugasan stasiun dilepas.');
    }

    public function supervisor(Request $request, Organization $organization, Event $event, EventStation $station)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $station->event_id === (int) $event->id, 404);
        $this->authorize('assignStations', $event);

        $data = $request->validate([
            'staff_membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
        ]);

        $membership = empty($data['staff_membership_id'])
            ? null
            : EventStaffMembership::query()
                ->whereKey($data['staff_membership_id'])
                ->where('event_id', $event->id)
                ->firstOrFail();

        $this->stations->setSupervisor($event, $station, $membership, $request->user());

        return back()->with('status', 'Supervisor stasiun diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Event $event, ?EventStation $station = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', Rule::enum(StationCategory::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'supervisor_membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
        ];
    }
}
