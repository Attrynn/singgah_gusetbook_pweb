<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StoryController extends Controller
{
    use ResolvesOwnerEvent;

    public function __invoke(Request $request, ReportService $reports)
    {
        $event = $this->ownerEvent($request);
        $stats = $reports->eventStats($event);

        $wishes = $event->messages()->where('status', 'approved')->latest()->take(6)->get();
        $photos = $event->mediaAssets()->where('status', 'approved')->latest()->take(9)->get();
        $timeline = $event->schedules()->get();
        $songs = $event->songRequests()->latest()->take(8)->get();

        return view('owner.story', compact('event', 'stats', 'wishes', 'photos', 'timeline', 'songs'));
    }
}
