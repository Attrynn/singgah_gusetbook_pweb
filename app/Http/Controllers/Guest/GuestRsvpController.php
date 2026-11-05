<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\Guest\GuestRsvpRequest;
use App\Services\RsvpService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestRsvpController extends Controller
{
    use ResolvesGuest;

    public function __construct(protected RsvpService $rsvps)
    {
    }

    public function store(GuestRsvpRequest $request)
    {
        $guest = $this->guest($request);
        $rsvp = $this->rsvps->submit($guest, $request->validated());

        $message = match ($rsvp->status->value) {
            'confirmed' => 'Terima kasih! Kehadiran Anda telah dikonfirmasi.',
            'declined' => 'Terima kasih atas kabarnya. Kami memahami ketidakhadiran Anda.',
            default => 'Terima kasih! Kami mencatat jawaban "mungkin hadir".',
        };

        return back()->with('status', $message);
    }
}
