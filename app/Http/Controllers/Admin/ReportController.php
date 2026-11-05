<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Models\Event;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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

    protected function rsvpRows(Event $event): array
    {
        $rows = [['Guest', 'Status', 'Attendance', 'Companions', 'Dietary', 'Responded At']];

        foreach ($event->rsvps()->with('guest')->get() as $rsvp) {
            $rows[] = [
                $rsvp->guest?->name,
                $rsvp->status->label(),
                $rsvp->attendance_count,
                $rsvp->companion_names,
                $rsvp->dietary_preference,
                $rsvp->responded_at?->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    protected function attendanceRows(Event $event): array
    {
        $rows = [['Guest', 'Method', 'Checked In At', 'Officer']];

        foreach ($event->checkins()->with(['guest', 'officer'])->get() as $checkin) {
            $rows[] = [
                $checkin->guest?->name,
                $checkin->method->label(),
                $checkin->checked_in_at?->format('Y-m-d H:i'),
                $checkin->officer?->name,
            ];
        }

        return $rows;
    }

    protected function messageRows(Event $event): array
    {
        $rows = [['Sender', 'Message', 'Visibility', 'Status', 'Created At']];

        foreach ($event->messages()->get() as $message) {
            $rows[] = [
                $message->sender_name,
                $message->message,
                $message->visibility->label(),
                $message->status->label(),
                $message->created_at?->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    protected function mediaRows(Event $event): array
    {
        $rows = [['File', 'Guest', 'Status', 'Featured', 'Uploaded At']];

        foreach ($event->mediaAssets()->with('guest')->get() as $asset) {
            $rows[] = [
                $asset->original_name,
                $asset->guest?->name,
                $asset->status->label(),
                $asset->is_featured ? 'Yes' : 'No',
                $asset->created_at?->format('Y-m-d H:i'),
            ];
        }

        return $rows;
    }

    protected function interactionRows(Event $event): array
    {
        $rows = [['Type', 'Detail', 'Value', 'Status']];

        foreach ($event->songRequests()->with('guest')->get() as $song) {
            $rows[] = ['Song', $song->song_title.' - '.$song->artist, $song->guest?->name, $song->status->label()];
        }

        foreach ($event->polls()->with('options')->get() as $poll) {
            $counts = $poll->responses()->selectRaw('poll_option_id, count(*) as total')->groupBy('poll_option_id')->pluck('total', 'poll_option_id');
            foreach ($poll->options as $option) {
                $rows[] = ['Poll', $poll->question.' / '.$option->label, (int) ($counts[$option->id] ?? 0), $poll->is_active ? 'Active' : 'Closed'];
            }
        }

        foreach ($event->games()->withCount('attempts')->get() as $game) {
            $rows[] = ['Game', $game->title, $game->attempts_count.' attempts', $game->is_active ? 'Active' : 'Closed'];
        }

        return $rows;
    }
}
