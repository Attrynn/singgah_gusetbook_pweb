<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\VenueSpaceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Venue;
use App\Models\VenueSpace;
use App\Services\VenueService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceVenueController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly VenueService $venues) {}

    public function index(Organization $organization, Event $event): View
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewVenues', $event);

        return view('workspace.events.venues.index', [
            'organization' => $organization,
            'event' => $event,
            'venues' => $this->venues->listForEvent($event),
            'spaceTypes' => VenueSpaceType::cases(),
            'canManage' => auth()->user()?->can('manageVenues', $event) ?? false,
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVenues', $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'google_maps_url' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $this->venues->create($event, $data, $request->user());

        return back()->with('status', 'Venue ditambahkan.');
    }

    public function update(Request $request, Organization $organization, Event $event, Venue $venue): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVenues', $event);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:2000'],
            'google_maps_url' => ['nullable', 'url', 'max:2048', 'starts_with:https://'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $this->venues->update($event, $venue, $data, $request->user());

        return back()->with('status', 'Venue diperbarui.');
    }

    public function archive(Request $request, Organization $organization, Event $event, Venue $venue): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVenues', $event);

        $this->venues->archive($event, $venue, $request->user());

        return back()->with('status', 'Venue diarsipkan.');
    }

    public function storeSpace(Request $request, Organization $organization, Event $event, Venue $venue): RedirectResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVenues', $event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::enum(VenueSpaceType::class)],
            'floor_label' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $this->venues->createSpace($event, $venue, $data, $request->user());

        return back()->with('status', 'Ruang venue ditambahkan.');
    }

    public function updateSpace(
        Request $request,
        Organization $organization,
        Event $event,
        Venue $venue,
        VenueSpace $space,
    ): RedirectResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVenues', $event);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'type' => ['sometimes', 'required', Rule::enum(VenueSpaceType::class)],
            'floor_label' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $this->venues->updateSpace($event, $venue, $space, $data, $request->user());

        return back()->with('status', 'Ruang venue diperbarui.');
    }

    public function archiveSpace(
        Request $request,
        Organization $organization,
        Event $event,
        Venue $venue,
        VenueSpace $space,
    ): RedirectResponse {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageVenues', $event);

        $this->venues->archiveSpace($event, $venue, $space, $request->user());

        return back()->with('status', 'Ruang venue diarsipkan.');
    }
}
