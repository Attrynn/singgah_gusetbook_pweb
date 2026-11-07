<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Models\Event;
use App\Models\PollResponse;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ExportsCsv;

    public function index(Request $request, ReportService $reports)
    {
        $events = Event::orderByDesc('event_date')->get();
        $event = $request->integer('event_id')
            ? $events->firstWhere('id', $request->integer('event_id'))
            : $events->first();

        $stats = $event ? $reports->eventStats($event) : [];
        $timeline = $event ? $reports->checkinTimeline($event) : [];
        $rsvpBreakdown = $event ? $reports->rsvpBreakdown($event) : [];
        $pollResults = $event ? $reports->pollResults($event) : collect();
        $gameResults = $event ? $reports->gameResults($event) : collect();
        $system = $reports->systemStats();

        return view('admin.reports.index', compact(
            'events', 'event', 'stats', 'timeline', 'rsvpBreakdown',
            'pollResults', 'gameResults', 'system'
        ));
    }

    public function export(Request $request, ReportService $reports)
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $type = $request->string('type', 'summary')->value();

        $rows = match ($type) {
            'guests' => $reports->guestReportRows($event),
            'rsvps' => $this->rsvpRows($event),
            'attendance' => $this->attendanceRows($event),
            'messages' => $this->messageRows($event),
            'media' => $this->mediaRows($event),
            'interactions' => $this->interactionRows($event),
            default => $this->summaryRows($event, $reports),
        };

        return $this->csvResponse($rows, "vowspace-{$type}-{$event->slug}.csv");
    }

    public function pdf(Request $request, ReportService $reports)
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $stats = $reports->eventStats($event);
        $rsvpBreakdown = $reports->rsvpBreakdown($event);
        $timeline = $reports->checkinTimeline($event);

        $pdf = Pdf::loadView('admin.reports.pdf', compact('event', 'stats', 'rsvpBreakdown', 'timeline'))
            ->setPaper('a4');

        return $pdf->download("vowspace-report-{$event->slug}.pdf");
    }

    protected function summaryRows(Event $event, ReportService $reports): array
    {
        $stats = $reports->eventStats($event);
        $rows = [['Metric', 'Value']];

        foreach ($stats as $key => $value) {
            $rows[] = [str_replace('_', ' ', ucfirst($key)), $value];
        }

        return $rows;
    }

    protected function rsvpRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write(['Guest', 'Status', 'Attendance', 'Companions', 'Dietary', 'Responded At']);

            $event->rsvps()->with('guest')->orderBy('id')->chunk(200, function ($rsvps) use ($write): void {
                foreach ($rsvps as $rsvp) {
                    $write([
                        $rsvp->guest?->name,
                        $rsvp->status->label(),
                        $rsvp->attendance_count,
                        $rsvp->companion_names,
                        $rsvp->dietary_preference,
                        $rsvp->responded_at?->format('Y-m-d H:i'),
                    ]);
                }
            });
        };
    }

    protected function attendanceRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write(['Guest', 'Method', 'Checked In At', 'Officer']);

            $event->checkins()->with(['guest', 'officer'])->orderBy('id')->chunk(200, function ($checkins) use ($write): void {
                foreach ($checkins as $checkin) {
                    $write([
                        $checkin->guest?->name,
                        $checkin->method->label(),
                        $checkin->checked_in_at?->format('Y-m-d H:i'),
                        $checkin->officer?->name,
                    ]);
                }
            });
        };
    }

    protected function messageRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write(['Sender', 'Message', 'Visibility', 'Status', 'Created At']);

            $event->messages()->orderBy('id')->chunk(200, function ($messages) use ($write): void {
                foreach ($messages as $message) {
                    $write([
                        $message->sender_name,
                        $message->message,
                        $message->visibility->label(),
                        $message->status->label(),
                        $message->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });
        };
    }

    protected function mediaRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write(['File', 'Guest', 'Status', 'Featured', 'Uploaded At']);

            $event->mediaAssets()->with('guest')->orderBy('id')->chunk(200, function ($assets) use ($write): void {
                foreach ($assets as $asset) {
                    $write([
                        $asset->original_name,
                        $asset->guest?->name,
                        $asset->status->label(),
                        $asset->is_featured ? 'Yes' : 'No',
                        $asset->created_at?->format('Y-m-d H:i'),
                    ]);
                }
            });
        };
    }

    protected function interactionRows(Event $event): callable
    {
        return function (callable $write) use ($event): void {
            $write(['Type', 'Detail', 'Value', 'Status']);

            $event->songRequests()->with('guest')->orderBy('id')->chunk(200, function ($songs) use ($write): void {
                foreach ($songs as $song) {
                    $write(['Song', $song->song_title.' - '.$song->artist, $song->guest?->name, $song->status->label()]);
                }
            });

            $polls = $event->polls()->with('options')->get();
            $counts = PollResponse::query()
                ->whereIn('poll_id', $polls->pluck('id'))
                ->select('poll_id', 'poll_option_id', DB::raw('count(*) as total'))
                ->groupBy('poll_id', 'poll_option_id')
                ->get()
                ->groupBy('poll_id');

            foreach ($polls as $poll) {
                $pollCounts = $counts->get($poll->id, collect())->pluck('total', 'poll_option_id');

                foreach ($poll->options as $option) {
                    $write(['Poll', $poll->question.' / '.$option->label, (int) ($pollCounts[$option->id] ?? 0), $poll->is_active ? 'Active' : 'Closed']);
                }
            }

            $event->games()->withCount('attempts')->get()->each(function ($game) use ($write): void {
                $write(['Game', $game->title, $game->attempts_count.' attempts', $game->is_active ? 'Active' : 'Closed']);
            });
        };
    }
}
