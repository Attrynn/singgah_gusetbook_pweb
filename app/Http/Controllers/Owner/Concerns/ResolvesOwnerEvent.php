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

        $id = $request->integer('event');

        if ($id) {
            $event = $events->firstWhere('id', $id);
            abort_unless($event, 403, 'Anda tidak memiliki akses ke event tersebut.');

            return $event;
        }

        return $events->first();
    }

    protected function ownerEvents(Request $request)
    {
        return $request->user()->events()->orderBy('event_date')->get();
    }
}
