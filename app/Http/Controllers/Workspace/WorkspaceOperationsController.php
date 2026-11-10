<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\OperationsStatus;
use App\Enums\RunOfShowStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventRunOfShowItem;
use App\Models\EventStation;
use App\Models\Organization;
use App\Services\OperationsDashboardService;
use App\Services\OperationsLifecycleService;
use App\Services\OperationsReadinessService;
use App\Services\TaskService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceOperationsController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(
        private readonly OperationsDashboardService $dashboard,
        private readonly OperationsLifecycleService $lifecycle,
        private readonly OperationsReadinessService $readiness,
        private readonly TaskService $tasks,
    ) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewOperations', $event);

        $user = request()->user();
        $summary = $this->dashboard->summary($event, $user);
        $operation = $event->operation()->first();

        return view('workspace.events.operations.index', [
            'organization' => $organization,
            'event' => $event,
            'operation' => $operation,
            'summary' => $summary,
            'readiness' => $summary['readiness'],
            'activity' => AuditLog::query()
                ->where('event_id', $event->id)
                ->with('user')
                ->latest()
                ->limit(30)
                ->get(),
            'unreadNotifications' => $user->unreadNotifications()->count(),
        ]);
    }

    public function live(Organization $organization, Event $event): JsonResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewOperations', $event);

        return response()->json($this->dashboard->summary($event, request()->user()));
    }

    public function myWork(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewOperations', $event);

        $user = request()->user();

        $memberships = $event->staffMemberships()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $membershipIds = $memberships->modelKeys();

        $stations = EventStation::query()
            ->where('event_id', $event->id)
            ->whereHas('activeAssignments', fn ($query) => $query->whereIn('staff_membership_id', $membershipIds))
            ->with(['supervisor.user'])
            ->get();

        $tasks = $membershipIds === []
            ? collect()
            : $this->tasks->visibleTasks($event, $user)
                ->whereIn('assigned_membership_id', $membershipIds)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->with(['station', 'assignee.user'])
                ->orderByRaw('due_at is null')
                ->orderBy('due_at')
                ->get();

        $schedule = $membershipIds === []
            ? collect()
            : EventRunOfShowItem::query()
                ->where('event_id', $event->id)
                ->where(function ($query) use ($membershipIds): void {
                    $query->whereIn('pic_staff_membership_id', $membershipIds)
                        ->orWhereHas('assignees', fn ($inner) => $inner->whereIn('event_staff_memberships.id', $membershipIds));
                })
                ->whereNotIn('status', [
                    RunOfShowStatus::Completed->value,
                    RunOfShowStatus::Skipped->value,
                    RunOfShowStatus::Cancelled->value,
                ])
                ->orderBy('planned_start')
                ->get();

        $announcements = EventAnnouncement::query()
            ->where('event_id', $event->id)
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('recipients', fn ($query) => $query->where('user_id', $user->id))
            ->latest('published_at')
            ->get();

        return view('workspace.events.operations.my-work', [
            'organization' => $organization,
            'event' => $event,
            'memberships' => $memberships,
            'stations' => $stations,
            'tasks' => $tasks,
            'schedule' => $schedule,
            'announcements' => $announcements,
            'readiness' => $this->readiness->assess($event),
        ]);
    }

    public function transition(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageOperations', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(OperationsStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'override' => ['sometimes', 'boolean'],
        ]);

        $this->lifecycle->transition(
            $event,
            OperationsStatus::from($data['status']),
            $request->user(),
            [
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'override' => $request->boolean('override'),
            ],
        );

        return back()->with('status', 'Status operasional diperbarui.');
    }

    public function closure(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageOperations', $event);

        return view('workspace.events.operations.closure', [
            'organization' => $organization,
            'event' => $event,
            'operation' => $event->operation()->first(),
            'review' => $this->lifecycle->closureReview($event),
            'readiness' => $this->readiness->assess($event),
        ]);
    }

    public function close(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('closeOperations', $event);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
            'override' => ['sometimes', 'boolean'],
        ]);

        $this->lifecycle->transition($event, OperationsStatus::Completed, $request->user(), [
            'notes' => $data['notes'] ?? null,
            'reason' => $data['reason'] ?? null,
            'override' => $request->boolean('override'),
        ]);

        return redirect()
            ->route('workspace.events.operations.index', [$organization, $event])
            ->with('status', 'Operasional event ditutup.');
    }

    public function reopen(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('closeOperations', $event);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->lifecycle->transition($event, OperationsStatus::Active, $request->user(), [
            'reason' => $data['reason'],
            'override' => true,
        ]);

        return redirect()
            ->route('workspace.events.operations.index', [$organization, $event])
            ->with('status', 'Operasional event dibuka kembali.');
    }
}
