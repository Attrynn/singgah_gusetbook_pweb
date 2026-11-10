<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\ReadinessStatus;
use App\Enums\StationStatus;
use App\Enums\TaskStatus;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\GuestPass;

/**
 * Explicit operational readiness assessment.
 *
 * Every condition is derived from persisted records. There are no hardcoded
 * percentages and optional conditions never silently block readiness.
 */
class OperationsReadinessService
{
    /**
     * @return array{status:ReadinessStatus,items:array<int,array{key:string,label:string,status:ReadinessStatus,message:string}>,blocking:int,warnings:int}
     */
    public function assess(Event $event): array
    {
        $items = [
            $this->eventConfiguration($event),
            $this->staffCoverage($event),
            $this->stationPreparation($event),
            $this->runSheetAvailability($event),
            $this->criticalTasks($event),
            $this->operationalContact($event),
            $this->checkinReadiness($event),
            $this->timezone($event),
        ];

        $blocking = collect($items)->where('status', ReadinessStatus::NotReady)->count();
        $warnings = collect($items)->where('status', ReadinessStatus::Warning)->count();

        $status = match (true) {
            $blocking > 0 => ReadinessStatus::NotReady,
            $warnings > 0 => ReadinessStatus::Warning,
            default => ReadinessStatus::Ready,
        };

        return [
            'status' => $status,
            'items' => $items,
            'blocking' => $blocking,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function eventConfiguration(Event $event): array
    {
        $missing = [];

        if ($event->event_date === null) {
            $missing[] = 'tanggal acara';
        }

        if (blank($event->venue_name)) {
            $missing[] = 'venue';
        }

        return [
            'key' => 'event_configuration',
            'label' => 'Konfigurasi acara',
            'status' => $missing === [] ? ReadinessStatus::Ready : ReadinessStatus::NotReady,
            'message' => $missing === []
                ? 'Tanggal dan venue acara sudah ditetapkan.'
                : 'Lengkapi: '.implode(', ', $missing).'.',
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function staffCoverage(Event $event): array
    {
        $active = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active->value)
            ->count();

        return [
            'key' => 'staff_coverage',
            'label' => 'Ketersediaan staf',
            'status' => $active > 0 ? ReadinessStatus::Ready : ReadinessStatus::NotReady,
            'message' => $active > 0
                ? "{$active} staf aktif terdaftar."
                : 'Belum ada staf aktif pada event ini.',
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function stationPreparation(Event $event): array
    {
        $stations = EventStation::query()->where('event_id', $event->id)->get();

        if ($stations->isEmpty()) {
            return [
                'key' => 'station_preparation',
                'label' => 'Persiapan stasiun',
                'status' => ReadinessStatus::Warning,
                'message' => 'Belum ada stasiun operasional. Stasiun bersifat opsional untuk event kecil.',
            ];
        }

        $withoutSupervisor = $stations->whereNull('supervisor_membership_id')->count();
        $notReady = $stations->filter(
            fn (EventStation $station) => in_array($station->status, [StationStatus::Draft, StationStatus::NotReady], true),
        )->count();

        $status = $withoutSupervisor > 0 || $notReady > 0
            ? ReadinessStatus::Warning
            : ReadinessStatus::Ready;

        return [
            'key' => 'station_preparation',
            'label' => 'Persiapan stasiun',
            'status' => $status,
            'message' => match (true) {
                $status === ReadinessStatus::Ready => "{$stations->count()} stasiun siap.",
                default => "{$withoutSupervisor} stasiun tanpa supervisor, {$notReady} stasiun belum siap.",
            },
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function runSheetAvailability(Event $event): array
    {
        $total = $event->runOfShowItems()->count();

        return [
            'key' => 'run_sheet',
            'label' => 'Run of show',
            'status' => $total > 0 ? ReadinessStatus::Ready : ReadinessStatus::Warning,
            'message' => $total > 0
                ? "{$total} item run of show tersedia."
                : 'Run of show belum diisi.',
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function criticalTasks(Event $event): array
    {
        $overdueCritical = EventTask::query()
            ->where('event_id', $event->id)
            ->where('priority', 'critical')
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->count();

        return [
            'key' => 'critical_tasks',
            'label' => 'Tugas kritis',
            'status' => $overdueCritical > 0 ? ReadinessStatus::NotReady : ReadinessStatus::Ready,
            'message' => $overdueCritical > 0
                ? "{$overdueCritical} tugas kritis melewati tenggat."
                : 'Tidak ada tugas kritis yang melewati tenggat.',
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function operationalContact(Event $event): array
    {
        $picId = $event->setupConfig()->first()?->primary_pic_staff_membership_id;

        return [
            'key' => 'operational_contact',
            'label' => 'Kontak operasional',
            'status' => $picId !== null ? ReadinessStatus::Ready : ReadinessStatus::Warning,
            'message' => $picId !== null
                ? 'Primary PIC sudah ditetapkan.'
                : 'Primary PIC belum ditetapkan pada persiapan acara.',
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function checkinReadiness(Event $event): array
    {
        $activePasses = GuestPass::query()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->count();

        if ($activePasses === 0) {
            return [
                'key' => 'checkin_readiness',
                'label' => 'Kesiapan check-in',
                'status' => ReadinessStatus::NotApplicable,
                'message' => 'Belum ada pass tamu aktif; check-in tidak relevan.',
            ];
        }

        $checkinRoles = array_filter(
            EventStaffRole::cases(),
            fn (EventStaffRole $role) => in_array(
                PermissionRegistry::CHECKIN_SCAN,
                PermissionRegistry::forEventStaffRole($role),
                true,
            ),
        );

        $hasOperator = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->where('status', MembershipStatus::Active->value)
            ->whereIn('role', array_map(fn ($role) => $role->value, $checkinRoles))
            ->exists();

        return [
            'key' => 'checkin_readiness',
            'label' => 'Kesiapan check-in',
            'status' => $hasOperator ? ReadinessStatus::Ready : ReadinessStatus::Warning,
            'message' => $hasOperator
                ? "{$activePasses} pass aktif dan operator check-in tersedia."
                : "{$activePasses} pass aktif tetapi belum ada staf check-in aktif.",
        ];
    }

    /**
     * @return array{key:string,label:string,status:ReadinessStatus,message:string}
     */
    protected function timezone(Event $event): array
    {
        $hasTimezone = filled($event->timezone);

        return [
            'key' => 'timezone',
            'label' => 'Zona waktu',
            'status' => $hasTimezone ? ReadinessStatus::Ready : ReadinessStatus::Warning,
            'message' => $hasTimezone
                ? "Zona waktu event: {$event->timezoneName()}."
                : 'Zona waktu belum disetel; digunakan default aplikasi.',
        ];
    }
}
