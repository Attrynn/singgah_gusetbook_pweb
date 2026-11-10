<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\MembershipStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\EventTaskChecklistItem;
use App\Models\EventTaskDependency;
use App\Models\Organization;
use App\Services\TaskService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceTaskController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewTasks', $event);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'station_id' => ['nullable', 'integer'],
        ]);

        $query = $this->tasks->visibleTasks($event, $request->user())
            ->with(['assignee.user', 'station', 'runOfShowItem', 'checklistItems'])
            ->withCount(['dependencies']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['station_id'])) {
            $query->where('station_id', $filters['station_id']);
        }

        return view('workspace.events.operations.tasks', [
            'organization' => $organization,
            'event' => $event,
            'tasks' => $query->orderByRaw('due_at is null')->orderBy('due_at')->get(),
            'staff' => $event->staffMemberships()
                ->with('user')
                ->where('status', MembershipStatus::Active->value)
                ->get(),
            'stations' => EventStation::query()->where('event_id', $event->id)->orderBy('name')->get(),
            'priorities' => TaskPriority::cases(),
            'statuses' => TaskStatus::cases(),
            'filters' => $filters,
            'canManage' => $request->user()->can('manageTasks', $event),
            'canCreate' => $request->user()->can('createTasks', $event),
            'canAssign' => $request->user()->can('assignTasks', $event),
        ]);
    }

    public function store(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('createTasks', $event);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'assigned_membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'run_of_show_item_id' => [
                'nullable',
                'integer',
                Rule::exists('event_run_of_show_items', 'id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date'],
        ]);

        $this->tasks->create($event, $data, $request->user());

        return back()->with('status', 'Tugas ditambahkan.');
    }

    public function update(Request $request, Organization $organization, Event $event, EventTask $task)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('manageTasks', $event);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'category' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'station_id' => [
                'nullable',
                'integer',
                Rule::exists('event_stations', 'id')->where('event_id', $event->id),
            ],
            'run_of_show_item_id' => [
                'nullable',
                'integer',
                Rule::exists('event_run_of_show_items', 'id')->where('event_id', $event->id),
            ],
            'due_at' => ['nullable', 'date'],
        ]);

        $this->tasks->update($event, $task, $data, $request->user());

        return back()->with('status', 'Tugas diperbarui.');
    }

    public function status(Request $request, Organization $organization, Event $event, EventTask $task)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('viewTasks', $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'reason' => ['nullable', 'string', 'max:500'],
            'override' => ['sometimes', 'boolean'],
        ]);

        $this->tasks->transition(
            $event,
            $task,
            TaskStatus::from($data['status']),
            $request->user(),
            $data['reason'] ?? null,
            $request->boolean('override'),
        );

        return back()->with('status', 'Status tugas diperbarui.');
    }

    public function assign(Request $request, Organization $organization, Event $event, EventTask $task)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('assignTasks', $event);

        $data = $request->validate([
            'assigned_membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
        ]);

        $membership = empty($data['assigned_membership_id'])
            ? null
            : EventStaffMembership::query()
                ->whereKey($data['assigned_membership_id'])
                ->where('event_id', $event->id)
                ->firstOrFail();

        $this->tasks->assign($event, $task, $membership, $request->user());

        return back()->with('status', 'Penanggung jawab tugas diperbarui.');
    }

    public function storeChecklist(Request $request, Organization $organization, Event $event, EventTask $task)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('viewTasks', $event);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $this->tasks->addChecklistItem($event, $task, $data['label'], $request->user());

        return back()->with('status', 'Item checklist ditambahkan.');
    }

    public function updateChecklist(Request $request, Organization $organization, Event $event, EventTask $task, EventTaskChecklistItem $item)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('viewTasks', $event);

        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'sequence' => ['sometimes', 'integer', 'min:0'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $this->tasks->updateChecklistItem($event, $task, $item, $data, $request->user());

        return back()->with('status', 'Checklist diperbarui.');
    }

    public function destroyChecklist(Organization $organization, Event $event, EventTask $task, EventTaskChecklistItem $item)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('manageTasks', $event);

        $this->tasks->deleteChecklistItem($event, $task, $item, request()->user());

        return back()->with('status', 'Item checklist dihapus.');
    }

    public function storeComment(Request $request, Organization $organization, Event $event, EventTask $task)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('viewTasks', $event);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->tasks->addComment($event, $task, $data['body'], $request->user());

        return back()->with('status', 'Komentar ditambahkan.');
    }

    public function storeDependency(Request $request, Organization $organization, Event $event, EventTask $task)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('manageTasks', $event);

        $data = $request->validate([
            'depends_on_task_id' => [
                'required',
                'integer',
                Rule::exists('event_tasks', 'id')->where('event_id', $event->id),
            ],
        ]);

        $dependsOn = EventTask::query()
            ->whereKey($data['depends_on_task_id'])
            ->where('event_id', $event->id)
            ->firstOrFail();

        $this->tasks->addDependency($event, $task, $dependsOn, $request->user());

        return back()->with('status', 'Dependensi tugas ditambahkan.');
    }

    public function destroyDependency(Organization $organization, Event $event, EventTask $task, EventTaskDependency $dependency)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $task->event_id === (int) $event->id, 404);
        $this->authorize('manageTasks', $event);

        $this->tasks->removeDependency($event, $task, $dependency, request()->user());

        return back()->with('status', 'Dependensi tugas dihapus.');
    }
}
