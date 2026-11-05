<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventActivity;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Services\ReportService;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports)
    {
        $stats = $reports->systemStats();

        $upcomingEvents = Event::query()
            ->withCount(['guests', 'rsvps'])
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->take(5)
            ->get();

        $recentActivities = EventActivity::with(['event', 'guest', 'user'])
            ->latest()
            ->take(10)
            ->get();

        $recentGuests = Guest::with('event')
            ->latest()
            ->take(8)
            ->get();

        $latestWishes = GuestbookMessage::with('event')
            ->latest()
            ->take(6)
            ->get();

        $statusOverview = collect(EventStatus::cases())->map(fn ($status) => [
            'status' => $status,
            'count' => Event::where('status', $status->value)->count(),
        ]);

        $rsvpChart = Event::query()
            ->selectRaw('events.name')
            ->selectRaw("SUM(CASE WHEN guests.rsvp_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed")
            ->selectRaw("SUM(CASE WHEN guests.rsvp_status = 'declined' THEN 1 ELSE 0 END) as declined")
            ->selectRaw("SUM(CASE WHEN guests.rsvp_status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->leftJoin('guests', 'guests.event_id', '=', 'events.id')
            ->groupBy('events.id', 'events.name')
            ->orderByDesc('events.event_date')
            ->limit(6)
            ->get();

        return view('admin.dashboard', compact(
            'stats', 'upcomingEvents', 'recentActivities', 'recentGuests',
            'latestWishes', 'statusOverview', 'rsvpChart'
        ));
    }
}
