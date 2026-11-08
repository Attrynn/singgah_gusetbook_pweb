<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\ScheduleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreEventScheduleRequest;
use App\Http\Requests\Workspace\UpdateEventScheduleRequest;
use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\Organization;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WorkspaceEventScheduleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageSchedule', $event);

        return view('workspace.events.schedule', [
            'organization' => $organization,
            'event' => $event,
            'schedules' => $event->schedules()->get(),
        ]);
    }

    public function store(StoreEventScheduleRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageSchedule', $event);

        $data = $request->validated();

        $schedule = EventSchedule::create([
            'event_id' => $event->id,
            ...$this->payload($event, $data, $this->nextSortOrder($event)),
        ]);

        $this->audit->record(
            'event.schedule.saved',
            "Jadwal publik ditambahkan: {$schedule->title}",
            $event,
            $request->user(),
            $schedule,
        );

        return back()->with('status', 'Jadwal ditambahkan.');
    }

    public function update(UpdateEventScheduleRequest $request, Organization $organization, Event $event, EventSchedule $schedule)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $schedule->event_id === (int) $event->id, 404);
        $this->authorize('manageSchedule', $event);

        $data = $request->validated();
        $sortOrder = isset($data['sort_order']) ? (int) $data['sort_order'] : (int) $schedule->sort_order;

        $schedule->update($this->payload($event, $data, $sortOrder));

        $this->audit->record(
            'event.schedule.saved',
            "Jadwal publik diperbarui: {$schedule->title}",
            $event,
            $request->user(),
            $schedule,
        );

        return back()->with('status', 'Jadwal diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventSchedule $schedule)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $schedule->event_id === (int) $event->id, 404);
        $this->authorize('manageSchedule', $event);

        $title = $schedule->title;
        $schedule->delete();

        $this->audit->record(
            'event.schedule.saved',
            "Jadwal publik dihapus: {$title}",
            $event,
            request()->user(),
        );

        return back()->with('status', 'Jadwal dihapus.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(Event $event, array $data, int $sortOrder): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $this->toUtc($data['start_time'] ?? null, $event->timezoneName()),
            'end_time' => $this->toUtc($data['end_time'] ?? null, $event->timezoneName()),
            'status' => $data['status'] ?? ScheduleStatus::Planned->value,
            'sort_order' => $sortOrder,
        ];
    }

    private function nextSortOrder(Event $event): int
    {
        return ((int) $event->schedules()->max('sort_order')) + 1;
    }

    /**
     * Interpret a wall-clock value in the event timezone and store its UTC instant.
     */
    private function toUtc(?string $value, string $timezone): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
