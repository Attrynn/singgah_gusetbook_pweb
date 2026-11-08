<?php

namespace App\Http\Controllers\Owner\Concerns;

use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

trait ResolvesOwnerEvent
{
    protected function ownerEvent(Request $request): Event
    {
        $events = $this->ownerEventsFor($request->user());

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

    protected function ownerEvents(Request $request): Collection
    {
        return $this->ownerEventsFor($request->user());
    }

    /**
     * Union legacy event_user rows with active event_owner_memberships rows so
     * that couples who accept a couple invitation can reach the owner portal.
     */
    protected function ownerEventsFor(User $user): Collection
    {
        $legacyEvents = $user->events()->orderBy('event_date')->get();

        $ownerEventIds = $user->eventOwnerMemberships()
            ->where('status', MembershipStatus::Active->value)
            ->pluck('event_id')
            ->all();

        $ownerEvents = Event::query()
            ->whereIn('id', $ownerEventIds)
            ->orderBy('event_date')
            ->get();

        return $legacyEvents->merge($ownerEvents)
            ->unique('id')
            ->sortBy('event_date')
            ->values();
    }
}
