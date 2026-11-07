<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\Guest\GuestPollRequest;
use App\Http\Requests\Guest\GuestSongRequest;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\SongRequest;
use App\Services\AuditService;
use App\Services\PollService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestInteractionController extends Controller
{
    use ResolvesGuest;

    public function __construct(
        protected PollService $polls,
        protected AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        abort_unless($this->can($request, 'interactions'), 403, 'Interaksi tamu tidak tersedia untuk acara ini.');

        $event = $this->event($request);
        $guest = $this->guest($request);

        $pollList = $event->polls()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->with(['options' => fn ($q) => $q->withCount('responses')])
            ->get();

        $pollIds = $pollList->pluck('id')->all();

        $myResponses = ! empty($pollIds)
            ? PollResponse::where('guest_id', $guest->id)
                ->whereIn('poll_id', $pollIds)
                ->with('option')
                ->get()
                ->keyBy('poll_id')
            : collect();

        $totals = ! empty($pollIds)
            ? PollResponse::whereIn('poll_id', $pollIds)
                ->selectRaw('poll_id, count(*) as count')
                ->groupBy('poll_id')
                ->pluck('count', 'poll_id')
            : collect();

        $polls = $pollList->map(fn ($poll) => [
            'poll' => $poll,
            'myResponse' => $myResponses->get($poll->id),
            'total' => (int) ($totals->get($poll->id) ?? 0),
        ]);

        $game = $event->games()->where('is_active', true)->first();
        $songs = $event->songRequests()->whereIn('status', ['approved', 'played'])->latest()->take(10)->get();

        return view('guest.interactions', compact('event', 'guest', 'polls', 'game', 'songs'));
    }

    public function song(GuestSongRequest $request)
    {
        abort_unless($this->can($request, 'song_request'), 403, 'Acara belum aktif.');

        $guest = $this->guest($request);
        $event = $this->event($request);

        $song = SongRequest::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'song_title' => $request->song_title,
            'artist' => $request->artist,
            'note' => $request->note,
        ]);

        $this->audit->guestAction($event, $guest, 'song.requested', "{$guest->name} meminta lagu {$song->song_title}", ['song_id' => $song->id]);

        return back()->with('status', 'Permintaan lagu terkirim.');
    }

    public function vote(GuestPollRequest $request, string $token, Poll $poll)
    {
        abort_unless($this->can($request, 'interactions'), 403, 'Interaksi tamu tidak tersedia untuk acara ini.');

        $event = $this->event($request);

        abort_unless($poll->event_id === $event->id, 404);

        $guest = $this->guest($request);
        $this->polls->vote($poll, $guest, $request->integer('poll_option_id'));

        return back()->with('status', 'Suara Anda tercatat.');
    }
}
