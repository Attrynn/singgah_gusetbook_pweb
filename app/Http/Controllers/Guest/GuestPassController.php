<?php

namespace App\Http\Controllers\Guest;

use App\Enums\TokenType;
use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Services\InvitationService;
use App\Services\QrService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class GuestPassController extends Controller
{
    use ResolvesGuest;

    public function __construct(
        protected InvitationService $invitations,
        protected QrService $qr,
    ) {
    }

    public function show(Request $request)
    {
        $guest = $this->guest($request);
        $event = $this->event($request);

        abort_unless($event->allowsGuestActions(), 403, 'Wedding pass belum aktif untuk acara ini.');

        $raw = $this->invitations->ensureQrToken($guest);
        $qrSvg = $this->qr->svg($raw, 300);

        return view('guest.pass', compact('guest', 'event', 'qrSvg'));
    }

    public function download(Request $request)
    {
        $guest = $this->guest($request);
        $event = $this->event($request);

        abort_unless($event->allowsGuestActions(), 403);

        $raw = $this->invitations->ensureQrToken($guest);
        $dataUri = $this->qr->pngDataUri($raw, 800);
        $png = base64_decode(Str::after($dataUri, 'base64,'), true);

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, 'vowspace-pass-'.Str::slug($guest->name).'.png', [
            'Content-Type' => 'image/png',
        ]);
    }
}
