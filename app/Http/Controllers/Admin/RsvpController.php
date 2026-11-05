<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RsvpStatus;
use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Requests\RsvpUpdateRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Rsvp;
use App\Services\RsvpService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RsvpController extends Controller
{
    use ExportsCsv;

    public function __construct(protected RsvpService $rsvps)
    {
    }

    public function index(Request $request)
    {
        $query = Rsvp::query()->with(['guest.category', 'guest.group', 'event']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $rsvps = $query->latest('responded_at')->paginate(20)->withQueryString();

        $statsQuery = Rsvp::query()->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id));

        $stats = [
            'confirmed' => (clone $statsQuery)->where('status', RsvpStatus::Confirmed->value)->count(),
            'declined' => (clone $statsQuery)->where('status', RsvpStatus::Declined->value)->count(),
            'maybe' => (clone $statsQuery)->where('status', RsvpStatus::Maybe->value)->count(),
            'attendance' => (int) (clone $statsQuery)->where('status', RsvpStatus::Confirmed->value)->sum('attendance_count'),
        ];

        return view('admin.rsvps.index', [
            'rsvps' => $rsvps,
            'events' => Event::orderBy('event_date')->get(),
            'stats' => $stats,
            'statuses' => RsvpStatus::cases(),
            'filters' => $request->only(['event_id', 'status', 'search']),
        ]);
    }

    public function update(RsvpUpdateRequest $request, Guest $guest)
    {
        $this->rsvps->manualUpdate($guest, $request->validated(), $request->user());

        return back()->with('status', 'RSVP '.$guest->name.' diperbarui.');
    }

    public function export(Request $request)
    {
        $eventId = $request->integer('event_id');
        $query = Rsvp::with(['guest', 'event'])->when($eventId, fn ($q) => $q->where('event_id', $eventId));

        $rows = [['Event', 'Guest', 'Status', 'Attendance', 'Companions', 'Dietary', 'Notes', 'Responded At']];

        foreach ($query->get() as $rsvp) {
            $rows[] = [
                $rsvp->event?->name,
                $rsvp->guest?->name,
                $rsvp->status->label(),
                $rsvp->attendance_count,
                $rsvp->companion_names,
                $rsvp->dietary_preference,
                $rsvp->notes,
                $rsvp->responded_at?->format('Y-m-d H:i'),
            ];
        }

        return $this->csvResponse($rows, 'vowspace-rsvp.csv');
    }
}
