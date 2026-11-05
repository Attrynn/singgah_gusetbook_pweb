<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\CheckinScanRequest;
use App\Http\Requests\ManualCheckinRequest;
use App\Models\Checkin;
use App\Models\Event;
use App\Models\Guest;
use App\Services\CheckinService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckinController extends Controller
{
    public function __construct(protected CheckinService $checkins)
    {
    }

    public function index(Request $request)
    {
        $query = Checkin::with(['guest', 'event', 'officer']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->whereHas('guest', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $checkins = $query->latest('checked_in_at')->paginate(20)->withQueryString();

        $stats = [
            'total' => Checkin::when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))->count(),
            'today' => Checkin::whereDate('checked_in_at', today())->count(),
            'qr' => Checkin::where('method', 'qr')->count(),
            'manual' => Checkin::where('method', 'manual')->count(),
        ];

        $guests = Guest::with('event')
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->whereNull('checked_in_at')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('admin.checkins.index', [
            'checkins' => $checkins,
            'events' => Event::orderBy('event_date')->get(),
            'guests' => $guests,
            'stats' => $stats,
            'filters' => $request->only(['event_id', 'search']),
        ]);
    }

    public function scanner(Request $request)
    {
        return view('admin.checkins.scanner', [
            'events' => Event::orderBy('event_date')->get(),
            'selectedEventId' => $request->integer('event_id'),
        ]);
    }

    public function scan(CheckinScanRequest $request)
    {
        $event = $request->integer('event_id') ? Event::find($request->integer('event_id')) : null;
        $result = $this->checkins->scan($request->string('token'), $request->user(), $event);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'],
                'warning' => $result['warning'],
                'guest' => $result['guest'] ? [
                    'id' => $result['guest']->id,
                    'name' => $result['guest']->name,
                    'rsvp' => $result['guest']->rsvp_status->label(),
                    'checked_in_at' => $result['guest']->checked_in_at?->toIso8601String(),
                ] : null,
            ]);
        }

        return back()->with('scan_result', [
            'status' => $result['status'],
            'message' => $result['message'],
            'warning' => $result['warning'],
            'guest_name' => $result['guest']?->name,
        ]);
    }

    public function manual(ManualCheckinRequest $request)
    {
        $guest = Guest::findOrFail($request->integer('guest_id'));
        $result = $this->checkins->manual($guest, $request->user(), $request->input('notes'));

        return back()->with('scan_result', [
            'status' => $result['status'],
            'message' => $result['message'],
            'warning' => $result['warning'],
            'guest_name' => $result['guest']?->name,
        ]);
    }
}
