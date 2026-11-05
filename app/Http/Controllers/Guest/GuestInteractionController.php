<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\Guest\GuestPollRequest;
use App\Http\Requests\Guest\GuestSongRequest;
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
    ) {
    }

    public function index(Request $request)
    {
        $event = $this->event($request);
        $guest = $this->guest($request);

        $polls = $event->polls()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->with('options')
            ->get()
            ->map(fn ($poll) => [
                'poll' => $poll,
                'myResponse' => $poll->responses()->where('guest_id', $guest->id)->first(),
                'total' => $poll->responses()->count(),
            ]);

        $game = $event->games()->where('is_active', true)->first();
        $songs = $event->songRequests()->whereIn('status', ['approved', 'played'])->latest()->take(10)->get();

        return view('guest.interactions', compact('event', 'guest', 'polls', 'game', 'songs'));
    }

    public function song(GuestSongRequest $request)
    {
        $guest = $this->guest($request);
        $event = $this->event($request);

        abort_unless($event->allowsGuestActions(), 403, 'Acara belum aktif.');

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

    public function vote(GuestPollRequest $request, string $token, \App\Models\Poll $poll)
    {
        $guest = $this->guest($request);
        $this->polls->vote($poll, $guest, $request->integer('poll_option_id'));

        return back()->with('status', 'Suara Anda tercatat.');
    }
}
