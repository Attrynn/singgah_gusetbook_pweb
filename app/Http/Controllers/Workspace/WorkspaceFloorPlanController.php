<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\FloorPlanObjectType;
use App\Enums\FloorPlanZoneType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\Guest;
use App\Models\Organization;
use App\Services\FloorPlanService;
use App\Services\FloorPlanVersionService;
use App\Services\SeatingAssignmentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceFloorPlanController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(
        private readonly FloorPlanService $floorPlans,
        private readonly FloorPlanVersionService $versions,
        private readonly SeatingAssignmentService $seating,
    ) {}

    public function index(Organization $organization, Event $event): View
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewFloorPlans', $event);

        return view('workspace.events.floor-plans.index', [
            'organization' => $organization,
            'event' => $event,
            'floorPlans' => $this->floorPlans->listForEvent($event),
            'canManage' => auth()->user()?->can('manageFloorPlans', $event) ?? false,
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlans', $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue_id' => ['nullable', 'integer'],
            'venue_space_id' => ['nullable', 'integer'],
            'canvas_width' => ['nullable', 'numeric', 'min:100', 'max:100000'],
            'canvas_height' => ['nullable', 'numeric', 'min:100', 'max:100000'],
            'grid_size' => ['nullable', 'numeric', 'min:1', 'max:1000'],
        ]);

        $plan = $this->floorPlans->create($event, $data, $request->user());

        return redirect()
            ->route('workspace.events.floor-plans.editor', [$organization, $event, $plan])
            ->with('status', 'Floor plan dibuat. Mulai susun layout pada canvas.');
    }

    public function show(Organization $organization, Event $event, FloorPlan $floorPlan): View
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewFloorPlans', $event);
        $this->floorPlans->assertBelongsToEvent($event, $floorPlan);

        $floorPlan->load(['venue', 'venueSpace', 'versions.creator', 'versions.publisher', 'currentDraft', 'publishedVersion']);

        return view('workspace.events.floor-plans.show', [
            'organization' => $organization,
            'event' => $event,
            'floorPlan' => $floorPlan,
            'canManage' => auth()->user()?->can('manageFloorPlans', $event) ?? false,
            'canPublish' => auth()->user()?->can('publishFloorPlans', $event) ?? false,
            'canDelete' => auth()->user()?->can('deleteFloorPlans', $event) ?? false,
        ]);
    }

    public function editor(Organization $organization, Event $event, FloorPlan $floorPlan): View
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewFloorPlans', $event);
        $this->floorPlans->assertBelongsToEvent($event, $floorPlan);

        $version = $floorPlan->currentDraft()->first()
            ?? $floorPlan->publishedVersion()->first()
            ?? $floorPlan->latestVersion()->first();

        abort_if($version === null, 404);

        $canManage = auth()->user()?->can('manageFloorPlans', $event) ?? false;

        return view('workspace.events.floor-plans.editor', [
            'organization' => $organization,
            'event' => $event,
            'floorPlan' => $floorPlan->load(['venue', 'venueSpace']),
            'version' => $version,
            'document' => $version->document,
            'tableSummary' => $this->seating->tableSummary($version),
            'guests' => Guest::query()
                ->where('event_id', $event->id)
                ->with(['activeMembers' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->limit(1000)
                ->get(),
            'objectTypes' => FloorPlanObjectType::cases(),
            'zoneTypes' => FloorPlanZoneType::cases(),
            'canManage' => $canManage,
            'canPublish' => auth()->user()?->can('publishFloorPlans', $event) ?? false,
            'canAssignSeating' => auth()->user()?->can('assignSeating', $event) ?? false,
            'isReadOnly' => ! $canManage || ! $version->isDraft(),
            'saveUrl' => route('workspace.events.floor-plans.versions.document', [$organization, $event, $floorPlan, $version]),
            'publishUrl' => route('workspace.events.floor-plans.versions.publish', [$organization, $event, $floorPlan, $version]),
            'validateUrl' => route('workspace.events.floor-plans.versions.validate', [$organization, $event, $floorPlan, $version]),
            'seatingUrl' => route('workspace.events.floor-plans.versions.seating.index', [$organization, $event, $floorPlan, $version]),
            'backUrl' => route('workspace.events.floor-plans.show', [$organization, $event, $floorPlan]),
        ]);
    }

    public function update(Request $request, Organization $organization, Event $event, FloorPlan $floorPlan): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlans', $event);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue_id' => ['nullable', 'integer'],
            'venue_space_id' => ['nullable', 'integer'],
        ]);

        $this->floorPlans->update($event, $floorPlan, $data, $request->user());

        return back()->with('status', 'Floor plan diperbarui.');
    }

    public function archive(Request $request, Organization $organization, Event $event, FloorPlan $floorPlan): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageFloorPlans', $event);

        $this->floorPlans->archive($event, $floorPlan, $request->user());

        return back()->with('status', 'Floor plan diarsipkan.');
    }

    public function destroy(Request $request, Organization $organization, Event $event, FloorPlan $floorPlan): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('deleteFloorPlans', $event);

        $this->floorPlans->delete($event, $floorPlan, $request->user());

        return redirect()
            ->route('workspace.events.floor-plans.index', [$organization, $event])
            ->with('status', 'Floor plan dihapus.');
    }
}
