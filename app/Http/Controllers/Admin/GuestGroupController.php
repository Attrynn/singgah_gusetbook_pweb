<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\GroupRequest;
use App\Models\Event;
use App\Models\GuestGroup;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class GuestGroupController extends Controller
{
    public function index(Request $request)
    {
        $events = Event::orderBy('event_date')->get();
        $event = $request->integer('event_id')
            ? $events->firstWhere('id', $request->integer('event_id'))
            : $events->first();

        return view('admin.groups.index', [
            'events' => $events,
            'event' => $event,
            'groups' => $event ? $event->groups()->withCount('guests')->orderBy('name')->get() : collect(),
        ]);
    }

    public function store(GroupRequest $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));

        if ($event->groups()->where('name', $request->name)->exists()) {
            throw ValidationException::withMessages(['name' => 'Kelompok ini sudah ada pada event.']);
        }

        $event->groups()->create($request->only(['name', 'description']));

        return back()->with('status', 'Kelompok tamu ditambahkan.');
    }

    public function update(GroupRequest $request, GuestGroup $group)
    {
        if ($group->event->groups()->where('name', $request->name)->whereKeyNot($group->id)->exists()) {
            throw ValidationException::withMessages(['name' => 'Kelompok ini sudah ada pada event.']);
        }

        $group->update($request->only(['name', 'description']));

        return back()->with('status', 'Kelompok tamu diperbarui.');
    }

    public function destroy(GuestGroup $group)
    {
        $group->delete();

        return back()->with('status', 'Kelompok tamu dihapus.');
    }
}
