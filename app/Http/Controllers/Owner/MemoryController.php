<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MemoryItemType;
use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Http\Requests\M9\MemoryModerateRequest;
use App\Models\Event;
use App\Models\EventMemorySettings;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Models\MemoryCollection;
use App\Services\AuditService;
use App\Services\MediaService;
use App\Services\MemoryFeedService;
use App\Services\MemoryModerationService;
use App\Services\MemorySettingsService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoryController extends Controller
{
    use ResolvesOwnerEvent;

    public function __construct(
        protected MemoryFeedService $feed,
        protected MemoryModerationService $moderation,
        protected MediaService $media,
        protected MemorySettingsService $settings,
        protected AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $event = $this->ownerEvent($request);

        $filters = $request->only(['album_id', 'date', 'collection', 'state']);

        if (! empty($filters['album_id'])) {
            $filters['album_id'] = (int) $filters['album_id'];
        } else {
            unset($filters['album_id']);
        }

        $state = $filters['state'] ?? 'approved';

        if (! in_array($state, ['pending', 'approved', 'hidden'], true)) {
            $state = 'approved';
        }

        $query = MediaAsset::query()->where('event_id', $event->id)->with('guest');

        if ($state === 'pending') {
            $query->where('status', 'pending');
        } elseif ($state === 'hidden') {
            $query->whereNotNull('hidden_at');
        } else {
            $query->where('status', 'approved')->whereNull('hidden_at');
        }

        if (! empty($filters['album_id'])) {
            $query->where('media_album_id', (int) $filters['album_id']);
        }

        if (! empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        if (! empty($filters['collection'])) {
            $collectionId = (int) $filters['collection'];

            $query->whereIn('id', function (Builder $subQuery) use ($collectionId, $event): void {
                $subQuery->select('item_id')
                    ->from('memory_collection_items')
                    ->where('memory_collection_id', $collectionId)
                    ->where('event_id', $event->id)
                    ->where('item_type', MemoryItemType::MediaAsset->value);
            });
        }

        $assets = $query->latest()->paginate(24)->withQueryString();

        $albums = $event->albums()->withCount(['assets' => fn ($q) => $q->where('status', 'approved')])->get();
        $collections = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get();

        return view('owner.memories.index', [
            'event' => $event,
            'assets' => $assets,
            'albums' => $albums,
            'collections' => $collections,
            'filters' => $filters,
            'settings' => EventMemorySettings::resolve($event),
            'stats' => $this->feed->stats($event),
        ]);
    }

    public function timeline(Request $request): View
    {
        $event = $this->ownerEvent($request);

        $filters = $request->only(['collection_id', 'from', 'to', 'type']);

        $rows = $this->feed->timeline($event, $filters, (int) config('memory.timeline_page_size', 30));

        $collections = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get();

        return view('owner.memories.timeline', [
            'event' => $event,
            'rows' => $rows,
            'filters' => $filters,
            'collections' => $collections,
            'settings' => EventMemorySettings::resolve($event),
            'stats' => $this->feed->stats($event),
        ]);
    }

    public function pending(Request $request): View
    {
        $event = $this->ownerEvent($request);

        $filters = $request->only(['collection_id', 'from', 'to', 'type']);

        $rows = $this->feed->pending($event, $filters, 30);

        $collections = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->get();

        return view('owner.memories.pending', [
            'event' => $event,
            'rows' => $rows,
            'filters' => $filters,
            'collections' => $collections,
            'settings' => EventMemorySettings::resolve($event),
            'stats' => $this->feed->stats($event),
        ]);
    }

    public function show(Request $request, int $asset): View
    {
        $event = $this->ownerEvent($request);
        $asset = $this->memoryAsset($event, $asset);
        $asset->load('guest', 'album', 'consent', 'guestPartyMembership', 'uploader');

        $contributor = $asset->consent?->contributor_name
            ?? $asset->guestPartyMembership?->name
            ?? $asset->guest?->name
            ?? $asset->uploader?->name;

        return view('owner.memories.show', compact('event', 'asset', 'contributor'));
    }

    public function moderate(MemoryModerateRequest $request, int $asset): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $mediaAsset = $this->memoryAsset($event, $asset);

        $state = $this->moderation->apply(
            MemoryItemType::MediaAsset->value,
            $mediaAsset->id,
            $request->validated('action'),
            $event,
            $request->user(),
            $request->validated('note'),
        );

        return back()->with('status', 'Status foto diperbarui: '.$state->label().'.');
    }

    public function download(Request $request, int $asset): StreamedResponse
    {
        $event = $this->ownerEvent($request);
        $asset = $this->memoryAsset($event, $asset);

        abort_unless($asset->event_id === $event->id && $asset->isApproved(), 403);

        $disk = $asset->storageDisk();
        abort_unless(Storage::disk($disk)->exists($asset->path), 404);

        $this->audit->record(
            'memory.media.downloaded',
            "Foto {$asset->original_name} diunduh oleh pemilik acara",
            $event,
            $request->user(),
            $asset,
        );

        return Storage::disk($disk)->download($asset->path, $asset->original_name);
    }

    public function storeAlbum(Request $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:500']]);

        $this->media->createAlbum($event, $data, $request->user());

        return back()->with('status', 'Album kenangan dibuat.');
    }

    public function destroyAlbum(Request $request, MediaAlbum $album): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        abort_unless($album->event_id === $event->id, 404);

        $this->media->deleteAlbum($album, $request->user());

        return back()->with('status', 'Album dihapus. Foto tetap tersimpan di galeri.');
    }

    protected function memoryAsset(Event $event, int $id): MediaAsset
    {
        return MediaAsset::query()->where('event_id', $event->id)->findOrFail($id);
    }
}
