<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SongStatus;
use App\Http\Requests\SongStatusRequest;
use App\Models\Event;
use App\Models\SongRequest;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SongController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $query = SongRequest::with(['guest', 'event']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        return view('admin.interactions.songs', [
            'songs' => $query->latest()->paginate(25)->withQueryString(),
            'events' => Event::orderBy('event_date')->get(),
            'statuses' => SongStatus::cases(),
            'filters' => $request->only(['event_id', 'status']),
        ]);
    }

    public function update(SongStatusRequest $request, SongRequest $song)
    {
        $song->update(['status' => $request->status]);
        $this->audit->record('song.status', "Status lagu {$song->song_title} diperbarui", $song->event, $request->user(), $song);

        return back()->with('status', 'Status permintaan lagu diperbarui.');
    }
}
