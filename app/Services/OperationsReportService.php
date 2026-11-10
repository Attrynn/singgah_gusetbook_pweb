<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventRunOfShowItem;
use App\Models\EventStaffMembership;
use App\Models\EventStation;
use App\Models\EventTask;
use App\Models\User;

/**
 * Event-scoped operational report rows. The caller decides CSV/PDF rendering
 * and applies the report export permission.
 */
class OperationsReportService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected StationService $stations,
    ) {}

    /**
     * @return array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function staffReport(Event $event, bool $includeContact = false): array
    {
        $memberships = EventStaffMembership::query()
            ->where('event_id', $event->id)
            ->with(['user', 'stationAssignments.station', 'permissionGrants'])
            ->orderBy('role')
            ->get();

        $headers = ['Nama', 'Peran', 'Status', 'Stasiun', 'Tugas Aktif', 'Izin Tambahan'];
        $rows = [];

        if ($includeContact) {
            $headers[] = 'Email';
        }

        foreach ($memberships as $membership) {
            $stationNames = $membership->stationAssignments
                ->whereNull('removed_at')
                ->map(fn ($assignment) => $assignment->station?->name)
                ->filter()
                ->unique()
                ->join(', ');

            $openTasks = EventTask::query()
                ->where('event_id', $event->id)
                ->where('assigned_membership_id', $membership->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();

            $row = [
                $membership->user?->name ?? '—',
                $membership->role->label(),
                $membership->status->label(),
                $stationNames !== '' ? $stationNames : '—',
                (string) $openTasks,
                $membership->permissionGrants->pluck('permission')->join(', ') ?: '—',
            ];

            if ($includeContact) {
                $row[] = $membership->user?->email ?? '—';
            }

            $rows[] = $row;
        }

        return [
            'title' => 'Laporan Staf',
            'filename' => 'laporan-staf-'.$event->id.'.csv',
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function stationReport(Event $event): array
    {
        $stations = EventStation::query()
            ->where('event_id', $event->id)
            ->with(['supervisor.user', 'activeAssignments.membership.user'])
            ->orderBy('name')
            ->get();

        $rows = $stations->map(function (EventStation $station): array {
            $coverage = $this->stations->coverage($station);

            return [
                $station->name,
                $station->category->label(),
                $station->status->label(),
                $station->supervisor?->user?->name ?? '—',
                (string) $coverage['assigned'],
                $coverage['label'],
                $station->location ?? '—',
            ];
        })->all();

        return [
            'title' => 'Laporan Stasiun',
            'filename' => 'laporan-stasiun-'.$event->id.'.csv',
            'headers' => ['Stasiun', 'Kategori', 'Status', 'Supervisor', 'Jumlah Staf', 'Cakupan', 'Lokasi'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function runSheetReport(Event $event): array
    {
        $items = EventRunOfShowItem::query()
            ->where('event_id', $event->id)
            ->with(['station', 'pic.user'])
            ->orderBy('sequence')
            ->get();

        $rows = $items->map(fn (EventRunOfShowItem $item): array => [
            (string) $item->sequence,
            $item->title,
            $item->planned_start?->toDateTimeString() ?? '—',
            $item->planned_end?->toDateTimeString() ?? '—',
            $item->actual_start?->toDateTimeString() ?? '—',
            $item->actual_end?->toDateTimeString() ?? '—',
            $item->status->label(),
            $item->station?->name ?? '—',
            $item->pic?->user?->name ?? '—',
        ])->all();

        return [
            'title' => 'Laporan Run of Show',
            'filename' => 'laporan-run-of-show-'.$event->id.'.csv',
            'headers' => ['Urutan', 'Judul', 'Rencana Mulai', 'Rencana Selesai', 'Aktual Mulai', 'Aktual Selesai', 'Status', 'Stasiun', 'PIC'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function taskReport(Event $event): array
    {
        $tasks = EventTask::query()
            ->where('event_id', $event->id)
            ->with(['assignee.user', 'station'])
            ->latest()
            ->get();

        $rows = $tasks->map(fn (EventTask $task): array => [
            $task->title,
            $task->priority->label(),
            $task->status->label(),
            $task->assignee?->user?->name ?? '—',
            $task->due_at?->toDateTimeString() ?? '—',
            $task->isOverdue() ? 'Ya' : 'Tidak',
            $task->station?->name ?? '—',
            $task->created_at?->toDateTimeString() ?? '—',
        ])->all();

        return [
            'title' => 'Laporan Tugas',
            'filename' => 'laporan-tugas-'.$event->id.'.csv',
            'headers' => ['Judul', 'Prioritas', 'Status', 'Penanggung Jawab', 'Tenggat', 'Terlambat', 'Stasiun', 'Dibuat'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function incidentReport(Event $event): array
    {
        $incidents = EventIncident::query()
            ->where('event_id', $event->id)
            ->with(['reporter', 'assignee', 'station'])
            ->latest('reported_at')
            ->get();

        $rows = $incidents->map(fn (EventIncident $incident): array => [
            $incident->title,
            $incident->category->label(),
            $incident->severity->label(),
            $incident->status->label(),
            $incident->reporter?->name ?? '—',
            $incident->assignee?->name ?? '—',
            $incident->reported_at?->toDateTimeString() ?? '—',
            $incident->resolved_at?->toDateTimeString() ?? '—',
            $incident->station?->name ?? '—',
        ])->all();

        return [
            'title' => 'Laporan Insiden',
            'filename' => 'laporan-insiden-'.$event->id.'.csv',
            'headers' => ['Judul', 'Kategori', 'Keparahan', 'Status', 'Pelapor', 'Penanggung Jawab', 'Dilaporkan', 'Diselesaikan', 'Stasiun'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}
     */
    public function attendanceReport(Event $event): array
    {
        $stats = $this->attendance->dashboardStats($event);

        $rows = [
            ['Kuota undangan (partai)', (string) $stats['invitations']['quota']],
            ['Jumlah partai undangan', (string) $stats['invitations']['parties']],
            ['Tamu teridentifikasi', (string) $stats['guests']['identified']],
            ['RSVP terkonfirmasi (partai)', (string) $stats['rsvp']['confirmed']],
            ['Jumlah kehadiran RSVP (legacy)', (string) $stats['rsvp']['attendance_count']],
            ['Pass diterbitkan', (string) $stats['passes']['issued']],
            ['Pass aktif', (string) $stats['passes']['active']],
            ['Check-in berhasil', (string) $stats['checkins']['total']],
            ['Belum check-in', (string) $stats['checkins']['not_checked_in']],
            ['Persentase check-in', $stats['checkins']['completion_percentage'].'%'],
        ];

        return [
            'title' => 'Laporan Kehadiran (M5)',
            'filename' => 'laporan-kehadiran-'.$event->id.'.csv',
            'headers' => ['Metrik', 'Nilai'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, array{title:string,filename:string,headers:array<int,string>,rows:array<int,array<int,string>>}>
     */
    public function all(Event $event, User $viewer, bool $includeContact = false): array
    {
        return [
            'staff' => $this->staffReport($event, $includeContact),
            'stations' => $this->stationReport($event),
            'run_sheet' => $this->runSheetReport($event),
            'tasks' => $this->taskReport($event),
            'incidents' => $this->incidentReport($event),
            'attendance' => $this->attendanceReport($event),
        ];
    }
}
