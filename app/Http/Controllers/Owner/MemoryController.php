<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MemoryController extends Controller
{
    use ResolvesOwnerEvent;

    public function index(Request $request)
    {
        $event = $this->ownerEvent($request);
        $query = $event->mediaAssets()->where('status', 'approved')->with('guest');

        if ($albumId = $request->integer('album_id')) {
            $query->where('media_album_id', $albumId);
        }

        if ($date = $request->string('date')->value()) {
            $query->whereDate('created_at', $date);
        }

        $assets = $query->latest()->paginate(24)->withQueryString();
        $albums = $event->albums()->withCount(['assets' => fn ($q) => $q->where('status', 'approved')])->get();

        return view('owner.memories.index', [
            'event' => $event,
            'assets' => $assets,
            'albums' => $albums,
            'filters' => $request->only(['album_id', 'date']),
            'totalPhotos' => $event->mediaAssets()->where('status', 'approved')->count(),
        ]);
    }

    public function show(Request $request, MediaAsset $asset)
    {
        $event = $this->ownerEvent($request);
        abort_unless($asset->event_id === $event->id && $asset->isApproved(), 403);
        $asset->load('guest', 'album');

        return view('owner.memories.show', compact('event', 'asset'));
    }

    public function download(Request $request, MediaAsset $asset)
    {
        $event = $this->ownerEvent($request);
        abort_unless($asset->event_id === $event->id && $asset->isApproved(), 403);
        abort_unless(Storage::disk('public')->exists($asset->path), 404);

        return Storage::disk('public')->download($asset->path, $asset->original_name);
    }

    public function storeAlbum(Request $request)
    {
        $event = $this->ownerEvent($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);

        $event->albums()->create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Album kenangan dibuat.');
    }

    public function destroyAlbum(Request $request, MediaAlbum $album)
    {
        $event = $this->ownerEvent($request);
        abort_unless($album->event_id === $event->id, 403);
        $album->delete();

        return back()->with('status', 'Album dihapus.');
    }
}
