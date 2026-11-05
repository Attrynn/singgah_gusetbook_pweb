<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InteractionController extends Controller
{
    use ResolvesOwnerEvent;

    public function __invoke(Request $request, ReportService $reports)
    {
        $event = $this->ownerEvent($request);
        $polls = $reports->pollResults($event);
        $games = $reports->gameResults($event);
        $songs = $event->songRequests()->with('guest')->latest()->take(30)->get();

        return view('owner.interactions', compact('event', 'polls', 'games', 'songs'));
    }
}
