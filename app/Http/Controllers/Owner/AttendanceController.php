<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceController extends Controller
{
    use ResolvesOwnerEvent;

    public function __invoke(Request $request, ReportService $reports)
    {
        $event = $this->ownerEvent($request);
        $stats = $reports->eventStats($event);
        $breakdown = $reports->rsvpBreakdown($event);
        $timeline = $reports->checkinTimeline($event);

        $latestCheckins = $event->checkins()
            ->with('guest')
            ->latest('checked_in_at')
            ->take(15)
            ->get();

        $confirmedGuests = $event->guests()
            ->where('rsvp_status', 'confirmed')
            ->orderBy('name')
            ->take(50)
            ->get();

        return view('owner.attendance', compact(
            'event', 'stats', 'breakdown', 'timeline', 'latestCheckins', 'confirmedGuests'
        ));
    }
}
