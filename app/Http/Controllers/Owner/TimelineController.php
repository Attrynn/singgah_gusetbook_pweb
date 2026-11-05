<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TimelineController extends Controller
{
    use ResolvesOwnerEvent;

    public function __invoke(Request $request)
    {
        $event = $this->ownerEvent($request);
        $schedules = $event->schedules()->get();

        return view('owner.timeline', compact('event', 'schedules'));
    }
}
