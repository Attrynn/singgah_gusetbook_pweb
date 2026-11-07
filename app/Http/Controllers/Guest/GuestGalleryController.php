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

    public function __construct(protected MediaService $media) {}

    public function index(Request $request)
    {
        abort_unless($this->can($request, 'gallery'), 403, 'Galeri tidak tersedia untuk acara ini.');

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
        abort_unless($this->can($request, 'gallery_upload'), 403, 'Galeri foto sedang tidak menerima unggahan.');

        $event = $this->event($request);

        $this->media->storeGuestUpload(
            $this->guest($request),
            $request->file('photo'),
            $request->input('caption')
        );

        return back()->with('status', 'Foto terkirim dan menunggu persetujuan admin.');
    }
}
