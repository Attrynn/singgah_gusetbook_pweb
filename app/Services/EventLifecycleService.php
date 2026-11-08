<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for event lifecycle transitions.
 *
 * The repository's real statuses are draft, published, live, completed and
 * archived. Any controller, service, job or UI that changes status must go
 * through this service so timestamps and guest capabilities stay consistent.
 */
class EventLifecycleService
{
    public function __construct(
        protected ?AuditService $audit = null,
    ) {
        $this->audit = $this->audit ?? app(AuditService::class);
    }

    /**
     * Allowed transitions keyed by the current status value.
     *
     * @var array<string, array<int, EventStatus>>
     */
    protected array $transitions = [
        EventStatus::Draft->value => [EventStatus::Published, EventStatus::Archived],
        EventStatus::Published->value => [EventStatus::Draft, EventStatus::Live, EventStatus::Archived],
        EventStatus::Live->value => [EventStatus::Completed, EventStatus::Archived],
        EventStatus::Completed->value => [EventStatus::Archived],
        EventStatus::Archived->value => [EventStatus::Published],
    ];

    public function canTransition(EventStatus $from, EventStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, $this->transitions[$from->value] ?? [], true);
    }

    /**
     * @return array<int, EventStatus>
     */
    public function allowedTransitions(EventStatus $from): array
    {
        return $this->transitions[$from->value] ?? [];
    }

    public function transition(Event $event, EventStatus $to, User $actor): Event
    {
        if ($event->status === $to) {
            return $event;
        }

        if (! $this->canTransition($event->status, $to)) {
            throw ValidationException::withMessages([
                'status' => "Transisi status dari {$event->status->label()} ke {$to->label()} tidak diizinkan.",
            ]);
        }

        return DB::transaction(function () use ($event, $to, $actor): Event {
            $oldStatus = $event->status;
            $event->update($this->payload($event, $to));

            $this->audit->recordCritical(
                'event.status_changed',
                "Status event {$event->name} diubah dari {$oldStatus->label()} ke {$to->label()}",
                $event,
                $actor,
                $event,
                ['from' => $oldStatus->value, 'to' => $to->value]
            );

            return $event->fresh();
        });
    }

    /**
     * Initial timestamp values for a newly created event.
     */
    public function initialPayload(EventStatus $status): array
    {
        return [
            'status' => $status,
            'published_at' => in_array($status, [EventStatus::Published, EventStatus::Live, EventStatus::Completed], true)
                ? now()
                : null,
            'archived_at' => $status === EventStatus::Archived ? now() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Event $event, EventStatus $to): array
    {
        return [
            'status' => $to,
            'published_at' => in_array($to, [EventStatus::Published, EventStatus::Live, EventStatus::Completed], true)
                ? ($event->published_at ?? now())
                : $event->published_at,
            'archived_at' => $to === EventStatus::Archived ? now() : null,
        ];
    }
}
