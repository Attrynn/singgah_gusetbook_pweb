<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Models\EventActivity;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    use ResolvesOwnerEvent;

    public function __invoke(Request $request, ReportService $reports)
    {
        $event = $this->ownerEvent($request);
        $stats = $reports->eventStats($event);

        $latestWishes = $event->messages()
            ->where('status', 'approved')
            ->latest()
            ->take(3)
            ->get();

        $latestPhotos = $event->mediaAssets()
            ->where('status', 'approved')
            ->latest()
            ->take(6)
            ->get();

        $nextSchedule = $event->schedules()
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->first();

        $activities = EventActivity::with('guest')
            ->where('event_id', $event->id)
            ->latest()
            ->take(6)
            ->get();

        return view('owner.dashboard', compact(
            'event', 'stats', 'latestWishes', 'latestPhotos', 'nextSchedule', 'activities'
        ));
    }
}
