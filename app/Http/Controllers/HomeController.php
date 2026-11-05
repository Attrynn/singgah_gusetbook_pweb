<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Routing\Controller;

class HomeController extends Controller
{
    public function __invoke()
    {
        if (auth()->check()) {
            return redirect()->route(
                auth()->user()->isSuperadmin() ? 'admin.dashboard' : 'owner.dashboard'
            );
        }

        $events = Event::query()
            ->whereIn('status', [EventStatus::Published->value, EventStatus::Live->value])
            ->upcoming()
            ->orderBy('event_date')
            ->take(3)
            ->get();

        return view('home', compact('events'));
    }
}
