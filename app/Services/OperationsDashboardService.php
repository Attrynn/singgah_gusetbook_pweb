<?php

namespace App\Services;

use App\Enums\AnnouncementStatus;
use App\Enums\IncidentStatus;
use App\Enums\MembershipStatus;
use App\Enums\OperationsStatus;
use App\Enums\RunOfShowStatus;
use App\Enums\StationStatus;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventAnnouncement;
use App\Models\EventAnnouncementRecipient;
use App\Models\EventIncident;
use App\Models\EventRunOfShowItem;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\User;

/**
 * Aggregated, event-scoped operational dashboard.
 *
 * Metrics come exclusively from persisted records. Attendance is delegated to
 * the authoritative M5 AttendanceService and never recomputed here.
 */
class OperationsDashboardService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected OperationsReadinessService $readiness,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(Event $event, User $viewer): array
    {
        $operation = $event->operation()->first();

        return [
            'operation' => [
                'status' => $operation?->status ?? OperationsStatus::Draft,
                'activated_at' => $operation?->activated_at,
                'completed_at' => $operation?->completed_at,
                'cancelled_at' => $operation?->cancelled_at,
            ],
            'readiness' => $this->readiness->assess($event),
            'staff' => $this->staffSummary($event),
            'stations' => $this->stationSummary($event),
            'run_sheet' => $this->runSheetSummary($event),
            'tasks' => $this->taskSummary($event),
            'incidents' => $this->incidentSummary($event),
            'announcements' => $this->announcementSummary($event, $viewer),
            'attendance' => $this->attendance->dashboardStats($event),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function staffSummary(Event $event): array
    {
        $base = EventStaffMembership::query()->where('event_id', $event->id);

        $active = (clone $base)->where('status', MembershipStatus::Active->value);

        $unassigned = (clone $active)
            ->whereDoesntHave('stationAssignments', fn ($query) => $query->whereNull('removed_at'))
            ->whereDoesntHave('assignedTasks', fn ($query) => $query->whereNotIn('status', [
                TaskStatus::Completed->value,
                TaskStatus::Cancelled->value,
            ]))
            ->count();

        return [
            'active' => (clone $active)->count(),
            'pending' => (clone $base)->where('status', MembershipStatus::Pending->value)->count(),
            'suspended' => (clone $base)->where('status', MembershipStatus::Suspended->value)->count(),
            'unassigned' => $unassigned,
            'supervisors' => EventStation::query()
                ->where('event_id', $event->id)
                ->whereNotNull('supervisor_membership_id')
                ->distinct()
                ->count('supervisor_membership_id'),
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function stationSummary(Event $event): array
    {
        $base = EventStation::query()->where('event_id', $event->id);

        return [
            'total' => (clone $base)->count(),
            'ready' => (clone $base)->whereIn('status', [StationStatus::Ready->value, StationStatus::Active->value])->count(),
            'active' => (clone $base)->where('status', StationStatus::Active->value)->count(),
            'without_staff' => (clone $base)->whereDoesntHave('activeAssignments')->count(),
            'temporarily_closed' => (clone $base)->where('status', StationStatus::TemporarilyClosed->value)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runSheetSummary(Event $event): array
    {
        $counts = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $current = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->where('status', RunOfShowStatus::InProgress->value)
            ->orderBy('planned_start')
            ->first();

        $next = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', [
                RunOfShowStatus::Completed->value,
                RunOfShowStatus::Skipped->value,
                RunOfShowStatus::Cancelled->value,
            ])
            ->where('planned_start', '>=', now())
            ->orderBy('planned_start')
            ->first();

        $upcoming = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->where('status', RunOfShowStatus::Pending->value)
            ->count();

        return [
            'total' => array_sum($counts),
            'pending' => (int) ($counts[RunOfShowStatus::Pending->value] ?? 0),
            'in_progress' => (int) ($counts[RunOfShowStatus::InProgress->value] ?? 0),
            'completed' => (int) ($counts[RunOfShowStatus::Completed->value] ?? 0),
            'delayed' => (int) ($counts[RunOfShowStatus::Delayed->value] ?? 0),
            'skipped' => (int) ($counts[RunOfShowStatus::Skipped->value] ?? 0),
            'cancelled' => (int) ($counts[RunOfShowStatus::Cancelled->value] ?? 0),
            'upcoming' => $upcoming,
            'current' => $current,
            'next' => $next,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function taskSummary(Event $event): array
    {
        $base = EventTask::query()->where('event_id', $event->id);

        $counts = (clone $base)
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $overdue = (clone $base)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        $criticalOpen = (clone $base)
            ->where('priority', 'critical')
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->count();

        return [
            'total' => array_sum($counts),
            'open' => (int) ($counts[TaskStatus::Open->value] ?? 0),
            'assigned' => (int) ($counts[TaskStatus::Assigned->value] ?? 0),
            'in_progress' => (int) ($counts[TaskStatus::InProgress->value] ?? 0),
            'blocked' => (int) ($counts[TaskStatus::Blocked->value] ?? 0),
            'completed' => (int) ($counts[TaskStatus::Completed->value] ?? 0),
            'cancelled' => (int) ($counts[TaskStatus::Cancelled->value] ?? 0),
            'overdue' => $overdue,
            'critical_open' => $criticalOpen,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function incidentSummary(Event $event): array
    {
        $base = EventIncident::query()->where('event_id', $event->id);

        $counts = (clone $base)
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $openStatuses = [
            IncidentStatus::Open->value,
            IncidentStatus::Acknowledged->value,
            IncidentStatus::InProgress->value,
        ];

        return [
            'total' => array_sum($counts),
            'open' => (clone $base)->whereIn('status', $openStatuses)->count(),
            'high' => (clone $base)->where('severity', 'high')->whereIn('status', $openStatuses)->count(),
            'critical' => (clone $base)->where('severity', 'critical')->whereIn('status', $openStatuses)->count(),
            'escalated' => (clone $base)->whereNotNull('escalated_at')->whereIn('status', $openStatuses)->count(),
            'resolved' => (int) ($counts[IncidentStatus::Resolved->value] ?? 0),
            'closed' => (int) ($counts[IncidentStatus::Closed->value] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function announcementSummary(Event $event, User $viewer): array
    {
        $visible = EventAnnouncement::query()
            ->where('event_id', $event->id)
            ->where('status', AnnouncementStatus::Published->value)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        $latest = (clone $visible)->latest('published_at')->limit(3)->get();

        $unacknowledgedForViewer = EventAnnouncementRecipient::query()
            ->where('user_id', $viewer->id)
            ->whereNull('acknowledged_at')
            ->whereHas('announcement', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('status', AnnouncementStatus::Published->value)
                ->where('priority', 'critical')
                ->where('requires_acknowledgement', true))
            ->count();

        $pendingAcknowledgements = EventAnnouncementRecipient::query()
            ->whereNull('acknowledged_at')
            ->whereHas('announcement', fn ($query) => $query
                ->where('event_id', $event->id)
                ->where('status', AnnouncementStatus::Published->value)
                ->where('requires_acknowledgement', true))
            ->count();

        return [
            'published' => (clone $visible)->count(),
            'latest' => $latest,
            'unacknowledged_for_viewer' => $unacknowledgedForViewer,
            'pending_acknowledgements' => $pendingAcknowledgements,
        ];
    }
}
