<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RsvpStatus;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\GameAttempt;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\Invitation;
use App\Models\MediaAsset;
use App\Models\PollResponse;
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
            'total_invitations' => Invitation::count(),
            'rsvp_confirmed' => Rsvp::where('status', RsvpStatus::Confirmed->value)->count(),
            'rsvp_declined' => Rsvp::where('status', RsvpStatus::Declined->value)->count(),
            'rsvp_pending' => Guest::where('rsvp_status', RsvpStatus::Pending->value)->count(),
            'total_checked_in' => Checkin::count(),
            'total_messages' => GuestbookMessage::count(),
            'total_photos' => MediaAsset::where('status', 'approved')->count(),
        ];
    }

    /**
     * Event statistics using aggregate queries rather than one query per metric.
     */
    public function eventStats(Event $event): array
    {
        $guestCounts = $event->guests()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN rsvp_status = ? THEN 1 ELSE 0 END) as confirmed', [RsvpStatus::Confirmed->value])
            ->selectRaw('SUM(CASE WHEN rsvp_status = ? THEN 1 ELSE 0 END) as declined', [RsvpStatus::Declined->value])
            ->selectRaw('SUM(CASE WHEN rsvp_status = ? THEN 1 ELSE 0 END) as pending', [RsvpStatus::Pending->value])
            ->selectRaw('SUM(CASE WHEN rsvp_status = ? THEN 1 ELSE 0 END) as maybe', [RsvpStatus::Maybe->value])
            ->selectRaw('SUM(CASE WHEN checked_in_at IS NOT NULL THEN 1 ELSE 0 END) as checked_in')
            ->first();

        $guestCount = (int) ($guestCounts->total ?? 0);
        $confirmed = (int) ($guestCounts->confirmed ?? 0);
        $declined = (int) ($guestCounts->declined ?? 0);
        $pending = (int) ($guestCounts->pending ?? 0);
        $maybe = (int) ($guestCounts->maybe ?? 0);
        $checkedIn = (int) ($guestCounts->checked_in ?? 0);
        $expectedAttendance = (int) $event->rsvps()->where('status', RsvpStatus::Confirmed->value)->sum('attendance_count');

        $messageCounts = $event->messages()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', ['approved'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', ['pending'])
            ->first();

        $mediaCounts = $event->mediaAssets()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', ['approved'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', ['pending'])
            ->first();

        $invitationCounts = $event->invitations()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened')
            ->first();

        $seating = $event->seatingTables()
            ->selectRaw('COUNT(*) as tables')
            ->selectRaw('COALESCE(SUM(capacity), 0) as capacity')
            ->first();

        return [
            'total_guests' => $guestCount,
            'confirmed' => $confirmed,
            'declined' => $declined,
            'pending' => $pending,
            'maybe' => $maybe,
            'checked_in' => $checkedIn,
            'expected_attendance' => $expectedAttendance,
            // Attendance rate is defined on guest records (checked-in guests / confirmed guests).
            // A separate headcount metric (expected_attendance) sums RSVP attendance_count.
            'attendance_rate' => $confirmed > 0 ? round(($checkedIn / $confirmed) * 100, 1) : 0.0,
            'rsvp_rate' => $guestCount > 0 ? round((($confirmed + $declined + $maybe) / $guestCount) * 100, 1) : 0.0,
            'total_messages' => (int) ($messageCounts->total ?? 0),
            'approved_messages' => (int) ($messageCounts->approved ?? 0),
            'pending_messages' => (int) ($messageCounts->pending ?? 0),
            'total_photos' => (int) ($mediaCounts->total ?? 0),
            'approved_photos' => (int) ($mediaCounts->approved ?? 0),
            'pending_photos' => (int) ($mediaCounts->pending ?? 0),
            'song_requests' => $event->songRequests()->count(),
            'total_invitations' => (int) ($invitationCounts->total ?? 0),
            'opened_invitations' => (int) ($invitationCounts->opened ?? 0),
            'tables' => (int) ($seating->tables ?? 0),
            'seat_capacity' => (int) ($seating->capacity ?? 0),
            'seated_guests' => $event->tableAssignments()->count(),
        ];
    }

    /**
     * @return array<int,array{label:string,count:int}>
     */
    public function checkinTimeline(Event $event): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:00', checked_in_at)",
            'pgsql' => "to_char(checked_in_at, 'YYYY-MM-DD HH24:00')",
            default => "DATE_FORMAT(checked_in_at, '%Y-%m-%d %H:00')",
        };

        $rows = $event->checkins()
            ->select(DB::raw("{$dateExpr} as bucket"), DB::raw('count(*) as total'))
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
        $counts = $event->guests()
            ->select('rsvp_status', DB::raw('count(*) as total'))
            ->groupBy('rsvp_status')
            ->pluck('total', 'rsvp_status');

        return collect(RsvpStatus::cases())->map(fn (RsvpStatus $status) => [
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ])->all();
    }

    public function pollResults(Event $event): Collection
    {
        $polls = $event->polls()->with('options')->get();

        $counts = PollResponse::query()
            ->whereIn('poll_id', $polls->pluck('id'))
            ->select('poll_id', 'poll_option_id', DB::raw('count(*) as total'))
            ->groupBy('poll_id', 'poll_option_id')
            ->get()
            ->groupBy('poll_id');

        return $polls->map(function ($poll) use ($counts) {
            $pollCounts = $counts->get($poll->id, collect())
                ->pluck('total', 'poll_option_id');
            $total = (int) $pollCounts->sum();

            return [
                'poll' => $poll,
                'total' => $total,
                'options' => $poll->options->map(fn ($option) => [
                    'label' => $option->label,
                    'count' => (int) ($pollCounts[$option->id] ?? 0),
                    'percent' => $total > 0 ? round(((int) ($pollCounts[$option->id] ?? 0) / $total) * 100, 1) : 0,
                ])->all(),
            ];
        });
    }

    public function gameResults(Event $event): Collection
    {
        $games = $event->games()->get();

        $aggregates = GameAttempt::query()
            ->whereIn('game_id', $games->pluck('id'))
            ->select(
                'game_id',
                DB::raw('count(*) as attempts'),
                DB::raw('avg(score) as average_score'),
                DB::raw('max(score) as best_score')
            )
            ->groupBy('game_id')
            ->get()
            ->keyBy('game_id');

        return $games->map(function ($game) use ($aggregates) {
            $row = $aggregates->get($game->id);

            return [
                'game' => $game,
                'attempts' => (int) ($row->attempts ?? 0),
                'average_score' => round((float) ($row->average_score ?? 0), 1),
                'best_score' => (int) ($row->best_score ?? 0),
            ];
        });
    }

    /**
     * Stream guest report rows from the database.
     */
    public function guestReportRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write([
                'Guest', 'Category', 'Group', 'RSVP', 'Attendance', 'Checked In', 'Max Attendees',
            ]);

            $event->guests()->with(['category', 'group', 'rsvp'])->orderBy('name')
                ->chunk(200, function ($guests) use ($write): void {
                    foreach ($guests as $guest) {
                        $write([
                            $guest->name,
                            $guest->category?->name ?? '-',
                            $guest->group?->name ?? '-',
                            $guest->rsvp_status->label(),
                            $guest->rsvp?->attendance_count ?? 0,
                            $guest->checked_in_at?->format('Y-m-d H:i') ?? '-',
                            $guest->max_attendees,
                        ]);
                    }
                });
        };
    }
}
