<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestGiftController extends Controller
{
    use ResolvesGuest;

    public function index(Request $request)
    {
        $event = $this->event($request);
        $gifts = $event->gift_enabled
            ? $event->giftAccounts()->where('is_active', true)->get()
            : collect();

        return view('guest.gift', [
            'event' => $event,
            'guest' => $this->guest($request),
            'gifts' => $gifts,
        ]);
    }
}
