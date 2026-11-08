<?php

namespace App\Services;

use App\Enums\SetupStatus;
use App\Models\Event;
use App\Models\EventPhase;
use App\Models\EventSession;
use App\Models\EventSetupChecklistItem;
use App\Models\EventSetupConfig;
use App\Models\PlaybookVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Applies playbook (or blank) configuration to an event and evaluates setup
 * readiness from the event's checklist. Never mutates event lifecycle status.
 */
class EventSetupService
{
    public function __construct(protected AuditService $audit) {}

    public function applyPlaybook(Event $event, PlaybookVersion $version, User $actor): EventSetupConfig
    {
        $config = $version->config ?? [];

        return DB::transaction(function () use ($event, $version, $config, $actor): EventSetupConfig {
            $setup = $this->persistSetup($event, [
                'playbook_id' => $version->playbook_id,
                'playbook_version_id' => $version->id,
                'source_event_id' => null,
                'config_snapshot' => $config,
                'setup_status' => SetupStatus::InProgress,
            ]);

            $this->syncStructure($event, $config);

            $this->audit->record(
                'event.setup.applied',
                "Playbook diterapkan pada event: {$event->name}",
                $event,
                $actor,
                $setup,
                ['playbook_id' => $version->playbook_id, 'version' => $version->version],
            );

            return $setup->fresh();
        });
    }

    public function applyBlank(Event $event, User $actor): EventSetupConfig
    {
        return DB::transaction(function () use ($event, $actor): EventSetupConfig {
            $setup = $this->persistSetup($event, [
                'playbook_id' => null,
                'playbook_version_id' => null,
                'source_event_id' => null,
                'config_snapshot' => [],
                'setup_status' => SetupStatus::InProgress,
            ]);

            $this->syncStructure($event, []);

            $this->audit->record(
                'event.setup.applied',
                "Konfigurasi kosong diterapkan pada event: {$event->name}",
                $event,
                $actor,
                $setup,
            );

            return $setup->fresh();
        });
    }

    /**
     * @return array{status: SetupStatus, items: Collection<int, EventSetupChecklistItem>, completed: int, total: int, percent: int}
     */
    public function evaluate(Event $event): array
    {
        $items = $event->checklistItems()->get();
        $total = $items->count();
        $completed = $items->where('completed', true)->count();
        $required = $items->where('required', true);
        $requiredTotal = $required->count();
        $requiredCompleted = $required->where('completed', true)->count();

        if ($total === 0) {
            $status = SetupStatus::NotStarted;
        } elseif (($requiredTotal > 0 && $requiredCompleted === $requiredTotal)
            || ($requiredTotal === 0 && $completed === $total)) {
            $status = SetupStatus::Ready;
        } else {
            $status = SetupStatus::InProgress;
        }

        return [
            'status' => $status,
            'items' => $items,
            'completed' => $completed,
            'total' => $total,
            'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
        ];
    }

    public function updateConfig(Event $event, array $data, User $actor): EventSetupConfig
    {
        return DB::transaction(function () use ($event, $data, $actor): EventSetupConfig {
            $setup = $event->setupConfig()->first()
                ?? $this->persistSetup($event, ['config_snapshot' => [], 'setup_status' => SetupStatus::InProgress]);

            $snapshot = $setup->config_snapshot ?? [];

            foreach (['phases', 'checklist', 'sessions', 'public_schedule', 'run_of_show', 'team_roles', 'vendor_categories', 'reception', 'invitation_defaults', 'guestbook_defaults'] as $key) {
                if (array_key_exists($key, $data)) {
                    $snapshot[$key] = $data[$key];
                }
            }

            $setup->update(['config_snapshot' => $snapshot]);

            $this->audit->record(
                'event.setup.updated',
                "Konfigurasi setup event diperbarui: {$event->name}",
                $event,
                $actor,
                $setup,
            );

            return $setup->fresh();
        });
    }

    /**
     * Persist the finalized readiness status computed from the checklist.
     * Never changes event lifecycle status.
     */
    public function finalize(Event $event, User $actor): EventSetupConfig
    {
        return DB::transaction(function () use ($event, $actor): EventSetupConfig {
            $evaluation = $this->evaluate($event);
            $status = $evaluation['status'] === SetupStatus::Ready
                ? SetupStatus::Ready
                : SetupStatus::InProgress;

            $setup = $this->persistSetup($event, ['setup_status' => $status]);

            $this->audit->record(
                'event.setup.finalized',
                "Setup event ditinjau: {$event->name}",
                $event,
                $actor,
                $setup,
                ['status' => $status->value, 'percent' => $evaluation['percent']],
            );

            return $setup->fresh();
        });
    }

    public function completeChecklistItem(Event $event, string $sourceKey, bool $completed, User $actor): void
    {
        $item = $event->checklistItems()->where('source_key', $sourceKey)->first();

        if (! $item) {
            return;
        }

        $item->update([
            'completed' => $completed,
            'completed_at' => $completed ? now() : null,
        ]);

        $this->audit->record(
            'event.setup.checklist',
            "Checklist '{$item->label}' {$event->name} ".($completed ? 'diselesaikan' : 'dibuka kembali'),
            $event,
            $actor,
            $item,
            ['source_key' => $sourceKey, 'completed' => $completed],
        );
    }

    /**
     * Create or update the single setup config row for the event.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function persistSetup(Event $event, array $attributes): EventSetupConfig
    {
        $setup = $event->setupConfig()->first();

        if ($setup) {
            $setup->update($attributes);

            return $setup;
        }

        return EventSetupConfig::create(['event_id' => $event->id, ...$attributes]);
    }

    /**
     * Rebuild phases and checklist rows from a config snapshot. Sessions and
     * staff assignments are intentionally never created here.
     *
     * @param  array<string, mixed>  $config
     */
    protected function syncStructure(Event $event, array $config): void
    {
        $event->checklistItems()->delete();
        $event->phases()->delete();

        $this->createPhases($event, $config['phases'] ?? []);
        $this->createChecklist($event, $config['checklist'] ?? []);

        if (array_key_exists('sessions', $config)) {
            $event->sessions()->delete();
            $this->createSessions($event, $config['sessions']);
        }
    }

    /**
     * Replace the event's session structure from wizard input.
     *
     * @param  array<int, array<string, mixed>>  $sessions
     */
    public function syncSessions(Event $event, array $sessions, User $actor): void
    {
        DB::transaction(function () use ($event, $sessions, $actor): void {
            $event->sessions()->delete();

            $this->createSessions($event, $sessions);

            $this->audit->record(
                'event.setup.sessions',
                "Struktur sesi event diperbarui: {$event->name}",
                $event,
                $actor,
                $event,
                ['count' => count($sessions)],
            );
        });
    }

    protected function createSessions(Event $event, mixed $sessions): void
    {
        if (! is_array($sessions)) {
            return;
        }

        $phaseIds = $event->phases()->orderBy('sequence')->pluck('id')->values();

        foreach (array_values($sessions) as $sequence => $session) {
            $name = is_array($session) ? ($session['name'] ?? null) : $session;

            if (blank($name)) {
                continue;
            }

            $phaseIndex = is_array($session) ? ($session['phase_index'] ?? null) : null;
            $sessionDate = is_array($session) ? ($session['session_date'] ?? null) : null;
            $timezone = is_array($session) ? ($session['timezone'] ?? null) : null;

            EventSession::create([
                'event_id' => $event->id,
                'phase_id' => is_numeric($phaseIndex) ? ($phaseIds[(int) $phaseIndex] ?? null) : null,
                'name' => $name,
                'session_date' => $sessionDate ?? $event->event_date,
                'start_time' => is_array($session) ? ($session['start_time'] ?? null) : null,
                'end_time' => is_array($session) ? ($session['end_time'] ?? null) : null,
                'timezone' => $timezone ?? $event->timezone ?? 'Asia/Jakarta',
                'sequence' => is_array($session) ? ($session['sequence'] ?? $sequence) : $sequence,
            ]);
        }
    }

    protected function createPhases(Event $event, mixed $phases): void
    {
        if (! is_array($phases)) {
            return;
        }

        foreach (array_values($phases) as $sequence => $phase) {
            $name = is_array($phase) ? ($phase['name'] ?? null) : $phase;

            if (blank($name)) {
                continue;
            }

            EventPhase::create([
                'event_id' => $event->id,
                'name' => $name,
                'sequence' => $phase['sequence'] ?? $sequence,
            ]);
        }
    }

    protected function createChecklist(Event $event, mixed $checklist): void
    {
        if (! is_array($checklist)) {
            return;
        }

        foreach (array_values($checklist) as $sequence => $item) {
            if (! is_array($item)) {
                continue;
            }

            $label = $item['label'] ?? null;

            if (blank($label)) {
                continue;
            }

            EventSetupChecklistItem::create([
                'event_id' => $event->id,
                'label' => $label,
                'description' => $item['description'] ?? null,
                'required' => (bool) ($item['required'] ?? false),
                'sequence' => $item['sequence'] ?? $sequence,
                'completed' => false,
                'completed_at' => null,
                'source_key' => $item['source_key'] ?? $item['key'] ?? null,
            ]);
        }
    }
}
