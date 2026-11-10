<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\IncidentStatus;
use App\Enums\OperationsStatus;
use App\Enums\ReadinessStatus;
use App\Enums\RunOfShowStatus;
use App\Enums\StationStatus;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventOperation;
use App\Models\EventRunOfShowItem;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central operational lifecycle state machine and closure workflow.
 *
 * Operational status is independent from the business EventStatus. Transitions
 * are validated, permission-checked, transactionally persisted, and audited.
 */
class OperationsLifecycleService
{
    public function __construct(
        protected AuditService $audit,
        protected PermissionService $permissions,
        protected OperationsReadinessService $readiness,
    ) {}

    public function ensure(Event $event): EventOperation
    {
        return EventOperation::firstOrCreate(
            ['event_id' => $event->id],
            ['status' => OperationsStatus::Draft],
        );
    }

    public function transition(Event $event, OperationsStatus $target, User $actor, array $options = []): EventOperation
    {
        $override = (bool) ($options['override'] ?? false);
        $reason = trim((string) ($options['reason'] ?? ''));

        return DB::transaction(function () use ($event, $target, $actor, $options, $override, $reason): EventOperation {
            $operation = EventOperation::query()
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first() ?? EventOperation::create([
                    'event_id' => $event->id,
                    'status' => OperationsStatus::Draft,
                ]);

            $from = $operation->status;

            $this->assertActorMayTransition($event, $actor, $from, $target);

            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => "Status operasional tidak dapat berubah dari {$from->label()} ke {$target->label()}.",
                ]);
            }

            $readiness = $this->readiness->assess($event);

            if ($target === OperationsStatus::Ready && $readiness['status'] === ReadinessStatus::NotReady && ! $override) {
                throw ValidationException::withMessages([
                    'status' => 'Kesiapan operasional belum terpenuhi. Selesaikan penghambat atau gunakan override dengan alasan.',
                ]);
            }

            if ($target === OperationsStatus::Active && $readiness['status'] !== ReadinessStatus::Ready && ! $override) {
                throw ValidationException::withMessages([
                    'status' => 'Operasional hanya dapat dimulai saat kesiapan berstatus Siap.',
                ]);
            }

            if ($target === OperationsStatus::Completed) {
                $review = $this->closureReview($event);

                if (! $review['can_close'] && ! $override) {
                    throw ValidationException::withMessages([
                        'status' => 'Penutupan operasional diblokir: '.implode(' ', array_column($review['blockers'], 'message')),
                    ]);
                }
            }

            if ($override && $reason === '') {
                throw ValidationException::withMessages(['reason' => 'Alasan override wajib diisi.']);
            }

            $payload = ['status' => $target];

            if ($target === OperationsStatus::Active) {
                $payload['activated_at'] = $operation->activated_at ?? now();
                $payload['completed_at'] = null;
                $payload['cancelled_at'] = null;
                $payload['closed_by'] = null;
            }

            if ($target === OperationsStatus::Completed) {
                $payload['completed_at'] = now();
                $payload['closed_by'] = $actor->id;
                $payload['closure_notes'] = $options['notes'] ?? null;
                $payload['closure_snapshot'] = $this->closureSnapshot($event, $options['notes'] ?? null);
                $payload['readiness_snapshot'] = $readiness;
            }

            if ($target === OperationsStatus::Cancelled) {
                $payload['cancelled_at'] = now();
            }

            $operation->update($payload);

            $this->audit->recordCritical(
                'operations.status_changed',
                "Status operasional {$event->name}: {$from->label()} → {$target->label()}",
                $event,
                $actor,
                $operation,
                [
                    'from' => $from->value,
                    'to' => $target->value,
                    'override' => $override,
                    'reason' => $reason !== '' ? $reason : null,
                ],
            );

            return $operation->fresh();
        });
    }

    /**
     * @return array{blockers:array<int,array{key:string,message:string}>,warnings:array<int,array{key:string,message:string}>,can_close:bool,summary:array<string,int>}
     */
    public function closureReview(Event $event): array
    {
        $openTasks = EventTask::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);

        $openTasksCount = (clone $openTasks)->count();
        $criticalOpenTasks = (clone $openTasks)->where('priority', 'critical')->count();

        $openIncidents = EventIncident::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                IncidentStatus::Open->value,
                IncidentStatus::Acknowledged->value,
                IncidentStatus::InProgress->value,
            ]);

        $openIncidentsCount = (clone $openIncidents)->count();
        $seriousOpenIncidents = (clone $openIncidents)->whereIn('severity', ['high', 'critical'])->count();

        $incompleteRunSheet = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                RunOfShowStatus::Pending->value,
                RunOfShowStatus::InProgress->value,
                RunOfShowStatus::Delayed->value,
            ])
            ->count();

        $incompleteStations = EventStation::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', StationStatus::Completed->value)
            ->count();

        $blockers = [];
        $warnings = [];

        if ($seriousOpenIncidents > 0) {
            $blockers[] = [
                'key' => 'serious_incidents',
                'message' => "{$seriousOpenIncidents} insiden berkeparahan tinggi/kritis masih terbuka.",
            ];
        }

        if ($criticalOpenTasks > 0) {
            $blockers[] = [
                'key' => 'critical_tasks',
                'message' => "{$criticalOpenTasks} tugas kritis belum selesai.",
            ];
        }

        if ($openTasksCount > 0) {
            $warnings[] = [
                'key' => 'open_tasks',
                'message' => "{$openTasksCount} tugas masih terbuka.",
            ];
        }

        if ($openIncidentsCount > 0) {
            $warnings[] = [
                'key' => 'open_incidents',
                'message' => "{$openIncidentsCount} insiden masih terbuka.",
            ];
        }

        if ($incompleteRunSheet > 0) {
            $warnings[] = [
                'key' => 'run_sheet_incomplete',
                'message' => "{$incompleteRunSheet} item run of show belum selesai.",
            ];
        }

        if ($incompleteStations > 0) {
            $warnings[] = [
                'key' => 'stations_incomplete',
                'message' => "{$incompleteStations} stasiun belum ditandai selesai.",
            ];
        }

        return [
            'blockers' => $blockers,
            'warnings' => $warnings,
            'can_close' => $blockers === [],
            'summary' => [
                'open_tasks' => $openTasksCount,
                'critical_open_tasks' => $criticalOpenTasks,
                'open_incidents' => $openIncidentsCount,
                'serious_open_incidents' => $seriousOpenIncidents,
                'incomplete_run_sheet_items' => $incompleteRunSheet,
                'incomplete_stations' => $incompleteStations,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function closureSnapshot(Event $event, ?string $notes): array
    {
        $review = $this->closureReview($event);

        return [
            'closed_at' => now()->toIso8601String(),
            'notes' => $notes,
            'summary' => $review['summary'],
            'readiness' => $this->readiness->assess($event),
            'stations' => EventStation::query()
                ->where('event_id', $event->id)
                ->get(['id', 'name', 'status'])
                ->map(fn (EventStation $station) => [
                    'id' => $station->id,
                    'name' => $station->name,
                    'status' => $station->status->value,
                ])
                ->all(),
        ];
    }

    protected function assertActorMayTransition(Event $event, User $actor, OperationsStatus $from, OperationsStatus $target): void
    {
        $requiresClose = $target === OperationsStatus::Completed
            || ($from === OperationsStatus::Completed && $target === OperationsStatus::Active);

        $permission = $requiresClose
            ? PermissionRegistry::OPERATIONS_CLOSE
            : PermissionRegistry::OPERATIONS_MANAGE;

        if (! $this->permissions->hasEventPermission($actor, $event, $permission)) {
            abort(403, $requiresClose
                ? 'Anda tidak berwenang menutup atau membuka kembali operasional event ini.'
                : 'Anda tidak berwenang mengubah status operasional event ini.');
        }
    }
}
