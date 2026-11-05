<?php

namespace App\Http\Controllers\Owner\Concerns;

use App\Models\Event;
use Illuminate\Http\Request;

trait ResolvesOwnerEvent
{
    protected function ownerEvent(Request $request): Event
    {
        $events = $request->user()->events()->orderBy('event_date')->get();

        abort_if($events->isEmpty(), 403, 'Akun Anda belum terhubung ke event pernikahan.');

        $id = $request->integer('event') ?: session('owner_active_event_id');

        if ($id) {
            $event = $events->firstWhere('id', (int) $id);
            if ($event) {
                session(['owner_active_event_id' => $event->id]);
                view()->share('currentOwnerEvent', $event);
                view()->share('ownerEventsList', $events);

                return $event;
            }
        }

        $event = $events->first();
        session(['owner_active_event_id' => $event->id]);
        view()->share('currentOwnerEvent', $event);
        view()->share('ownerEventsList', $events);

        return $event;
    }

    protected function ownerEvents(Request $request)
    {
        return $request->user()->events()->orderBy('event_date')->get();
    }
}
