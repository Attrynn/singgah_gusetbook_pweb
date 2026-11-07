<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Models\Guest;
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
    ) {}

    public function show(Request $request)
    {
        abort_unless($this->can($request, 'pass'), 403, 'Wedding pass belum aktif untuk acara ini.');

        $guest = $this->guest($request);
        $event = $this->event($request);

        $this->ensurePassStillValid($guest);

        $raw = $this->invitations->ensureQrToken($guest);
        $qrSvg = $this->qr->svg($raw, 300);

        return view('guest.pass', compact('guest', 'event', 'qrSvg'));
    }

    public function download(Request $request)
    {
        abort_unless($this->can($request, 'pass'), 403);

        $guest = $this->guest($request);
        $event = $this->event($request);

        $this->ensurePassStillValid($guest);

        $raw = $this->invitations->ensureQrToken($guest);
        $dataUri = $this->qr->pngDataUri($raw, 800);
        $png = base64_decode(Str::after($dataUri, 'base64,'), true);

        return response()->streamDownload(function () use ($png) {
            echo $png;
        }, 'vowspace-pass-'.Str::slug($guest->name).'.png', [
            'Content-Type' => 'image/png',
        ]);
    }

    /**
     * Never render a pass that the check-in desk would reject.
     */
    protected function ensurePassStillValid(Guest $guest): void
    {
        $expiresAt = $this->invitations->qrExpiresAt($guest);

        abort_if($expiresAt && $expiresAt->isPast(), 403, 'Wedding pass sudah kedaluwarsa. Hubungi admin acara untuk pass baru.');
    }
}
