<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStatus;
use App\Enums\RunOfShowStatus;
use App\Enums\ScheduleStatus;
use App\Enums\SetupStatus;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventRunOfShowItem;
use App\Models\EventSchedule;
use App\Models\EventSession;
use App\Models\EventSetupChecklistItem;
use App\Models\EventSetupConfig;
use App\Models\EventVenue;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Duplicates an event by copying an explicit allowlist of structural and
 * identity data. Personal, transactional and token data plus all user
 * assignments are never copied.
 */
class EventBlueprintService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * Static description of what a duplicate copies and what it excludes.
     *
     * @return array{identity: array<int, string>, structure: array<int, string>, playbook_refs: array<int, string>, excluded: array<int, string>}
     */
    public static function allowedCopyMap(): array
    {
        return [
            'identity' => [
                'name', 'groom_name', 'bride_name', 'couple_description', 'description',
                'venue_name', 'venue_address', 'google_maps_url', 'event_date',
                'start_time', 'end_time', 'timezone',
            ],
            'structure' => [
                'primary_venue', 'setup_config', 'checklist', 'phases', 'sessions',
                'event_schedules', 'run_of_show_items',
            ],
            'playbook_refs' => ['playbook_id', 'playbook_version_id'],
            'excluded' => [
                'guests', 'guest_party_memberships', 'guest_passes', 'guest_tokens', 'invitations', 'rsvps', 'checkins', 'media',
                'guestbook_messages', 'gifts', 'event_user', 'event_owner_memberships',
                'event_staff_memberships', 'audit_logs', 'published_at', 'archived_at',
            ],
        ];
    }

    public function duplicate(Event $source, array $options, User $actor): Event
    {
        if (! $source->organization_id) {
            abort(422, 'Event sumber belum terhubung ke workspace.');
        }

        $permissions = app(PermissionService::class);

        if (! $actor->isSuperadmin()
            && ! $actor->isPlatformSuperadmin()
            && ! $permissions->hasEventPermission($actor, $source, PermissionRegistry::EVENT_UPDATE)) {
            abort(422, 'Anda tidak berwenang menduplikasi event ini.');
        }

        return DB::transaction(function () use ($source, $options, $actor): Event {
            $event = Event::create([
                'name' => $options['name'] ?? ($source->name.' (Salinan)'),
                'organization_id' => $source->organization_id,
                'created_by' => $actor->id,
                'status' => EventStatus::Draft,
                'event_date' => $options['event_date'] ?? $source->event_date,
                'timezone' => $source->timezone,
                'groom_name' => $source->groom_name,
                'bride_name' => $source->bride_name,
                'couple_description' => $source->couple_description,
                'description' => $source->description,
                'venue_name' => $source->venue_name,
                'venue_address' => $source->venue_address,
                'google_maps_url' => $source->google_maps_url,
                'start_time' => $source->start_time,
                'end_time' => $source->end_time,
            ]);

            $this->copyPrimaryVenue($source, $event);
            $this->copySetupConfig($source, $event, $options);
            $this->copyChecklist($source, $event, $options);

            $phaseMap = $this->copyPhases($source, $event, $options);
            $sessionMap = $this->copySessions($source, $event, $options, $phaseMap);

            $this->copySchedules($source, $event, $options);
            $this->copyRunOfShow($source, $event, $options, $phaseMap, $sessionMap, $actor);

            $this->audit->record(
                'event.duplicated',
                "Event diduplikasi: {$source->name} -> {$event->name}",
                $event,
                $actor,
                $event,
                ['source_event_id' => $source->id],
            );

            return $event->fresh(['primaryVenue', 'setupConfig', 'phases', 'sessions', 'runOfShowItems']);
        });
    }

    protected function copyPrimaryVenue(Event $source, Event $event): void
    {
        $venue = $source->primaryVenue()->first();

        if (! $venue) {
            return;
        }

        EventVenue::create([
            'event_id' => $event->id,
            'name' => $venue->name,
            'address' => $venue->address,
            'google_maps_url' => $venue->google_maps_url,
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'is_primary' => true,
            'sort_order' => $venue->sort_order,
        ]);
    }

    protected function copySetupConfig(Event $source, Event $event, array $options): void
    {
        if (! $this->shouldCopy($options, 'setup')) {
            return;
        }

        $setup = $source->setupConfig()->first();

        if (! $setup) {
            return;
        }

        EventSetupConfig::create([
            'event_id' => $event->id,
            'playbook_id' => $setup->playbook_id,
            'playbook_version_id' => $setup->playbook_version_id,
            'source_event_id' => $source->id,
            'config_snapshot' => $setup->config_snapshot ?? [],
            'setup_status' => SetupStatus::InProgress,
            'primary_pic_staff_membership_id' => null,
        ]);
    }

    protected function copyChecklist(Event $source, Event $event, array $options): void
    {
        if (! $this->shouldCopy($options, 'checklist')) {
            return;
        }

        foreach ($source->checklistItems()->get() as $item) {
            EventSetupChecklistItem::create([
                'event_id' => $event->id,
                'label' => $item->label,
                'description' => $item->description,
                'required' => $item->required,
                'sequence' => $item->sequence,
                'completed' => false,
                'completed_at' => null,
                'source_key' => $item->source_key,
            ]);
        }
    }

    /**
     * @return array<int, int> Map of old phase id => new phase id.
     */
    protected function copyPhases(Event $source, Event $event, array $options): array
    {
        $map = [];

        if (! $this->shouldCopy($options, 'phases')) {
            return $map;
        }

        foreach ($source->phases()->get() as $phase) {
            $copy = EventPhase::create([
                'event_id' => $event->id,
                'name' => $phase->name,
                'sequence' => $phase->sequence,
            ]);

            $map[$phase->id] = $copy->id;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $phaseMap
     * @return array<int, int> Map of old session id => new session id.
     */
    protected function copySessions(Event $source, Event $event, array $options, array $phaseMap): array
    {
        $map = [];

        if (! $this->shouldCopy($options, 'sessions')) {
            return $map;
        }

        foreach ($source->sessions()->get() as $session) {
            $copy = EventSession::create([
                'event_id' => $event->id,
                'phase_id' => $session->phase_id ? ($phaseMap[$session->phase_id] ?? null) : null,
                'name' => $session->name,
                'session_date' => $session->session_date,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'timezone' => $session->timezone,
                'sequence' => $session->sequence,
            ]);

            $map[$session->id] = $copy->id;
        }

        return $map;
    }

    protected function copySchedules(Event $source, Event $event, array $options): void
    {
        if (! $this->shouldCopy($options, 'schedule')) {
            return;
        }

        foreach ($source->schedules()->get() as $schedule) {
            EventSchedule::create([
                'event_id' => $event->id,
                'title' => $schedule->title,
                'description' => $schedule->description,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'status' => $schedule->status ?? ScheduleStatus::Planned,
                'sort_order' => $schedule->sort_order,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $phaseMap
     * @param  array<int, int>  $sessionMap
     */
    protected function copyRunOfShow(Event $source, Event $event, array $options, array $phaseMap, array $sessionMap, User $actor): void
    {
        if (! $this->shouldCopy($options, 'run_of_show')) {
            return;
        }

        foreach ($source->runOfShowItems()->get() as $item) {
            EventRunOfShowItem::create([
                'event_id' => $event->id,
                'event_session_id' => $item->event_session_id ? ($sessionMap[$item->event_session_id] ?? null) : null,
                'phase_id' => $item->phase_id ? ($phaseMap[$item->phase_id] ?? null) : null,
                'title' => $item->title,
                'internal_description' => $item->internal_description,
                'planned_start' => $item->planned_start,
                'planned_end' => $item->planned_end,
                'actual_start' => null,
                'actual_end' => null,
                'status' => RunOfShowStatus::Pending,
                'sequence' => $item->sequence,
                'pic_staff_membership_id' => null,
                'internal_notes' => null,
                'created_by' => $actor->id,
                'updated_by' => null,
            ]);
        }
    }

    /**
     * Structural copy is on by default; either `key` or `copy_key` may be used.
     */
    protected function shouldCopy(array $options, string $key): bool
    {
        return (bool) ($options[$key] ?? $options['copy_'.$key] ?? true);
    }
}
