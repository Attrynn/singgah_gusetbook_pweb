<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Models\Event;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestInvitationController extends Controller
{
    use ResolvesGuest;

    public function __construct(protected InvitationService $invitations)
    {
    }

    public function landing(string $slug)
    {
        $event = Event::publiclyVisible()->where('slug', $slug)->firstOrFail();

        return view('guest.landing', compact('event'));
    }

    public function show(Request $request)
    {
        $guest = $this->guest($request);
        $event = $this->event($request);
        $token = (string) $request->route('token');

        $event->load(['schedules', 'primaryVenue', 'giftAccounts']);

        $messages = $event->messages()->publicVisible()->latest()->take(6)->get();
        $photos = $event->mediaAssets()->approved()->latest()->take(6)->get();

        $poll = $event->polls()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->with('options')
            ->latest()
            ->first();

        $game = $event->games()->where('is_active', true)->first();

        $myPollResponse = $poll
            ? $poll->responses()->where('guest_id', $guest->id)->first()
            : null;

        if (! $event->archived_at) {
            $this->invitations->registerOpen($guest);
        }

        return view('guest.invitation', [
            'event' => $event,
            'guest' => $guest,
            'token' => $token,
            'preview' => false,
            'messages' => $messages,
            'photos' => $photos,
            'poll' => $poll,
            'game' => $game,
            'myPollResponse' => $myPollResponse,
            'rsvpEnabled' => (bool) $event->rsvp_enabled,
            'rsvpOpen' => $event->rsvpOpen(),
            'guestbookEnabled' => (bool) $event->guestbook_enabled,
            'galleryEnabled' => (bool) $event->gallery_enabled,
            'giftEnabled' => (bool) $event->gift_enabled,
            'gifts' => $event->giftAccounts,
            'allowsGuestActions' => $event->allowsGuestActions(),
        ]);
    }

    public function markShared(Request $request)
    {
        $guest = $this->guest($request);
        $this->invitations->markShared($guest);

        return back()->with('status', 'Terima kasih. Status berbagi undangan dicatat.');
    }
}
