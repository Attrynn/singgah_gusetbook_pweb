<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RsvpStatus;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\Rsvp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function systemStats(): array
    {
        return [
            'total_events' => Event::count(),
            'active_events' => Event::whereIn('status', [EventStatus::Published->value, EventStatus::Live->value])->count(),
            'completed_events' => Event::where('status', EventStatus::Completed->value)->count(),
            'archived_events' => Event::where('status', EventStatus::Archived->value)->count(),
            'total_guests' => Guest::count(),
            'total_invitations' => \App\Models\Invitation::count(),
            'rsvp_confirmed' => Rsvp::where('status', RsvpStatus::Confirmed->value)->count(),
            'rsvp_declined' => Rsvp::where('status', RsvpStatus::Declined->value)->count(),
            'rsvp_pending' => Guest::where('rsvp_status', RsvpStatus::Pending->value)->count(),
            'total_checked_in' => Checkin::count(),
            'total_messages' => GuestbookMessage::count(),
            'total_photos' => MediaAsset::where('status', 'approved')->count(),
        ];
    }

    public function eventStats(Event $event): array
    {
        $guestCount = $event->guests()->count();
        $confirmed = $event->guests()->where('rsvp_status', RsvpStatus::Confirmed->value)->count();
        $declined = $event->guests()->where('rsvp_status', RsvpStatus::Declined->value)->count();
        $pending = $event->guests()->where('rsvp_status', RsvpStatus::Pending->value)->count();
        $maybe = $event->guests()->where('rsvp_status', RsvpStatus::Maybe->value)->count();
        $checkedIn = $event->guests()->whereNotNull('checked_in_at')->count();
        $expectedAttendance = (int) $event->rsvps()->where('status', RsvpStatus::Confirmed->value)->sum('attendance_count');

        return [
            'total_guests' => $guestCount,
            'confirmed' => $confirmed,
            'declined' => $declined,
            'pending' => $pending,
            'maybe' => $maybe,
            'checked_in' => $checkedIn,
            'expected_attendance' => $expectedAttendance,
            'attendance_rate' => $confirmed > 0 ? round(($checkedIn / $confirmed) * 100, 1) : 0.0,
            'rsvp_rate' => $guestCount > 0 ? round((($confirmed + $declined + $maybe) / $guestCount) * 100, 1) : 0.0,
            'total_messages' => $event->messages()->count(),
            'approved_messages' => $event->messages()->where('status', 'approved')->count(),
            'pending_messages' => $event->messages()->where('status', 'pending')->count(),
            'total_photos' => $event->mediaAssets()->count(),
            'approved_photos' => $event->mediaAssets()->where('status', 'approved')->count(),
            'pending_photos' => $event->mediaAssets()->where('status', 'pending')->count(),
            'song_requests' => $event->songRequests()->count(),
            'total_invitations' => $event->invitations()->count(),
            'opened_invitations' => $event->invitations()->whereNotNull('opened_at')->count(),
            'tables' => $event->seatingTables()->count(),
            'seat_capacity' => (int) $event->seatingTables()->sum('capacity'),
            'seated_guests' => $event->tableAssignments()->count(),
        ];
    }

    /**
     * @return array<int,array{label:string,count:int}>
     */
    public function checkinTimeline(Event $event): array
    {
        $rows = $event->checkins()
            ->select(DB::raw("DATE_FORMAT(checked_in_at, '%Y-%m-%d %H:00') as bucket"), DB::raw('count(*) as total'))
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        return $rows->map(fn ($row) => [
            'label' => $row->bucket,
            'count' => (int) $row->total,
        ])->all();
    }

    /**
     * @return array<int,array{label:string,count:int}>
     */
    public function rsvpBreakdown(Event $event): array
    {
        return collect(RsvpStatus::cases())->map(fn (RsvpStatus $status) => [
            'label' => $status->label(),
            'count' => $event->guests()->where('rsvp_status', $status->value)->count(),
        ])->all();
    }

    public function pollResults(Event $event): Collection
    {
        return $event->polls()->with('options')->get()->map(function ($poll) {
            $counts = $poll->responses()
                ->select('poll_option_id', DB::raw('count(*) as total'))
                ->groupBy('poll_option_id')
                ->pluck('total', 'poll_option_id');
            $total = (int) $counts->sum();

            return [
                'poll' => $poll,
                'total' => $total,
                'options' => $poll->options->map(fn ($option) => [
                    'label' => $option->label,
                    'count' => (int) ($counts[$option->id] ?? 0),
                    'percent' => $total > 0 ? round(((int) ($counts[$option->id] ?? 0) / $total) * 100, 1) : 0,
                ])->all(),
            ];
        });
    }

    public function gameResults(Event $event): Collection
    {
        return $event->games()->withCount('attempts')->get()->map(function ($game) {
            return [
                'game' => $game,
                'attempts' => $game->attempts_count,
                'average_score' => round((float) $game->attempts()->avg('score'), 1),
                'best_score' => (int) $game->attempts()->max('score'),
            ];
        });
    }

    public function guestReportRows(Event $event): array
    {
        $rows = [[
            'Guest', 'Category', 'Group', 'RSVP', 'Attendance', 'Checked In', 'Max Attendees',
        ]];

        $event->guests()->with(['category', 'group', 'rsvp'])->orderBy('name')->chunk(200, function ($guests) use (&$rows) {
            foreach ($guests as $guest) {
                $rows[] = [
                    $guest->name,
                    $guest->category?->name ?? '-',
                    $guest->group?->name ?? '-',
                    $guest->rsvp_status->label(),
                    $guest->rsvp?->attendance_count ?? 0,
                    $guest->checked_in_at?->format('Y-m-d H:i') ?? '-',
                    $guest->max_attendees,
                ];
            }
        });

        return $rows;
    }
}
