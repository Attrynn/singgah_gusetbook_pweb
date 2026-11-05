<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaStatus;
use App\Http\Requests\MediaAlbumRequest;
use App\Http\Requests\MediaUploadRequest;
use App\Models\Event;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class MediaController extends Controller
{
    public function __construct(protected MediaService $media) {}

    public function index(Request $request)
    {
        $query = MediaAsset::with(['event', 'guest', 'album']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        if ($albumId = $request->integer('album_id')) {
            $query->where('media_album_id', $albumId);
        }

        $assets = $query->latest()->paginate(24)->withQueryString();

        $eventId = $request->integer('event_id');
        $stats = [
            'total' => MediaAsset::when($eventId, fn ($q, $id) => $q->where('event_id', $id))->count(),
            'pending' => MediaAsset::when($eventId, fn ($q, $id) => $q->where('event_id', $id))->where('status', MediaStatus::Pending->value)->count(),
            'approved' => MediaAsset::when($eventId, fn ($q, $id) => $q->where('event_id', $id))->where('status', MediaStatus::Approved->value)->count(),
            'featured' => MediaAsset::when($eventId, fn ($q, $id) => $q->where('event_id', $id))->where('is_featured', true)->count(),
        ];

        return view('admin.media.index', [
            'assets' => $assets,
            'events' => Event::orderBy('event_date')->get(),
            'albums' => MediaAlbum::withCount('assets')->get(),
            'stats' => $stats,
            'statuses' => MediaStatus::cases(),
            'filters' => $request->only(['event_id', 'status', 'album_id']),
        ]);
    }

    public function store(MediaUploadRequest $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $album = $request->integer('media_album_id') ? MediaAlbum::find($request->integer('media_album_id')) : null;
        $count = 0;

        foreach ($request->file('files', []) as $file) {
            $this->media->storeEventUpload($event, $file, $request->user(), $album, true);
            $count++;
        }

        return back()->with('status', "{$count} foto diunggah ke galeri.");
    }

    public function moderate(Request $request, MediaAsset $asset)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'pending'])]]);
        $this->media->moderate($asset, $data['action'], $request->user());

        return back()->with('status', 'Status foto diperbarui.');
    }

    public function feature(Request $request, MediaAsset $asset)
    {
        $this->media->toggleFeatured($asset, $request->user());

        return back()->with('status', 'Status unggulan foto diperbarui.');
    }

    public function destroy(Request $request, MediaAsset $asset)
    {
        $this->media->delete($asset, $request->user());

        return back()->with('status', 'Foto dihapus.');
    }

    public function storeAlbum(MediaAlbumRequest $request)
    {
        $this->media->createAlbum(Event::findOrFail($request->integer('event_id')), $request->validated(), $request->user());

        return back()->with('status', 'Album dibuat.');
    }

    public function destroyAlbum(Request $request, MediaAlbum $album)
    {
        $this->media->deleteAlbum($album, $request->user());

        return back()->with('status', 'Album dihapus.');
    }

    public function attach(Request $request, MediaAlbum $album)
    {
        $data = $request->validate([
            'media_asset_id' => [
                'required',
                'integer',
                Rule::exists('media_assets', 'id')->where('event_id', $album->event_id),
            ],
        ]);
        $this->media->attachToAlbum($album, MediaAsset::findOrFail($data['media_asset_id']));

        return back()->with('status', 'Foto ditambahkan ke album.');
    }
}
