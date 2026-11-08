<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Services\EventBlueprintService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class WorkspaceEventDuplicateController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EventBlueprintService $blueprint,
    ) {}

    public function store(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('update', $event);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date'],
            'setup' => ['nullable', 'boolean'],
            'checklist' => ['nullable', 'boolean'],
            'phases' => ['nullable', 'boolean'],
            'sessions' => ['nullable', 'boolean'],
            'schedule' => ['nullable', 'boolean'],
            'run_of_show' => ['nullable', 'boolean'],
        ]);

        // The blueprint service copies the source event into the same
        // organization (forced via source->organization_id), never trusting an
        // organization from the request body.
        $copy = $this->blueprint->duplicate($event, $data, $request->user());

        return redirect()->route('workspace.events.show', [$organization, $copy])
            ->with('status', 'Event berhasil diduplikasi.');
    }

    private function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
