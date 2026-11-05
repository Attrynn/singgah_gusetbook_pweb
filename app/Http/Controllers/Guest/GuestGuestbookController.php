<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\Guest\GuestMessageRequest;
use App\Services\GuestbookService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestGuestbookController extends Controller
{
    use ResolvesGuest;

    public function __construct(protected GuestbookService $guestbook)
    {
    }

    public function store(GuestMessageRequest $request)
    {
        $guest = $this->guest($request);
        $message = $this->guestbook->submit($guest, $request->validated());

        $status = $message->isPublic()
            ? 'Ucapan terkirim. Pesan publik akan tampil setelah disetujui.'
            : 'Pesan privat terkirim. Hanya pasangan dan admin yang dapat membacanya.';

        return back()->with('status', $status);
    }
}
