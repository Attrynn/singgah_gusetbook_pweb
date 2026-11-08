<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\MembershipStatus;
use App\Enums\RunOfShowStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreRunOfShowItemRequest;
use App\Http\Requests\Workspace\UpdateRunOfShowItemRequest;
use App\Models\Event;
use App\Models\EventRunOfShowItem;
use App\Models\Organization;
use App\Services\RunOfShowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WorkspaceRunOfShowController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly RunOfShowService $runOfShow,
    ) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewRunOfShow', $event);

        return view('workspace.events.run-of-show', [
            'organization' => $organization,
            'event' => $event,
            'items' => $event->runOfShowItems()
                ->with(['session', 'phase', 'pic.user', 'creator', 'updater'])
                ->get(),
            'sessions' => $event->sessions()->get(),
            'phases' => $event->phases()->get(),
            'staff' => $event->staffMemberships()
                ->with('user')
                ->where('status', MembershipStatus::Active->value)
                ->get(),
            'statuses' => RunOfShowStatus::cases(),
        ]);
    }

    public function store(StoreRunOfShowItemRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageRunOfShow', $event);

        $this->runOfShow->create($event, $request->validated(), $request->user());

        return back()->with('status', 'Item run of show ditambahkan.');
    }

    public function update(UpdateRunOfShowItemRequest $request, Organization $organization, Event $event, EventRunOfShowItem $item)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        $this->authorize('manageRunOfShow', $event);

        $this->runOfShow->update($item, $request->validated(), $request->user());

        return back()->with('status', 'Item run of show diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventRunOfShowItem $item)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $item->event_id === (int) $event->id, 404);
        $this->authorize('manageRunOfShow', $event);

        $this->runOfShow->delete($item, request()->user());

        return back()->with('status', 'Item run of show dihapus.');
    }

    private function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
