<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\Guest\GuestPhotoRequest;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestGalleryController extends Controller
{
    use ResolvesGuest;

    public function __construct(protected MediaService $media)
    {
    }

    public function index(Request $request)
    {
        $event = $this->event($request);

        $assets = $event->mediaAssets()
            ->approved()
            ->with('guest')
            ->latest()
            ->paginate(24);

        return view('guest.gallery', [
            'event' => $event,
            'guest' => $this->guest($request),
            'assets' => $assets,
        ]);
    }

    public function store(GuestPhotoRequest $request)
    {
        $event = $this->event($request);

        abort_unless($event->gallery_enabled && $event->allowsGuestActions(), 403, 'Galeri foto sedang tidak menerima unggahan.');

        $this->media->storeGuestUpload(
            $this->guest($request),
            $request->file('photo'),
            $request->input('caption')
        );

        return back()->with('status', 'Foto terkirim dan menunggu persetujuan admin.');
    }
}
