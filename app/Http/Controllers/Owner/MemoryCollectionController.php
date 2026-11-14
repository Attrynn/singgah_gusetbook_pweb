<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MemoryItemType;
use App\Http\Controllers\Owner\Concerns\ResolvesOwnerEvent;
use App\Http\Requests\M9\MemoryCollectionRequest;
use App\Models\Event;
use App\Models\MemoryCollection;
use App\Services\MemoryCollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MemoryCollectionController extends Controller
{
    use ResolvesOwnerEvent;

    public function __construct(protected MemoryCollectionService $collections) {}

    public function index(Request $request): View
    {
        $event = $this->ownerEvent($request);

        $active = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->withCount('items')
            ->get();

        $archived = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNotNull('archived_at')
            ->latest('archived_at')
            ->withCount('items')
            ->get();

        return view('owner.memories.collections', [
            'event' => $event,
            'collections' => $active,
            'archived' => $archived,
            'itemTypes' => MemoryItemType::options(),
        ]);
    }

    public function store(MemoryCollectionRequest $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $this->collections->create($event, $request->validated(), $request->user());

        return back()->with('status', 'Koleksi memori dibuat.');
    }

    public function update(MemoryCollectionRequest $request, int $collection): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $target = $this->memoryCollection($event, $collection);

        $this->collections->update($target, $request->validated(), $request->user());

        return back()->with('status', 'Koleksi memori diperbarui.');
    }

    public function destroy(Request $request, int $collection): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $target = $this->memoryCollection($event, $collection);

        $this->collections->destroy($target, $request->user());

        return back()->with('status', 'Koleksi memori dihapus permanen.');
    }

    public function archive(Request $request, int $collection): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $target = $this->memoryCollection($event, $collection);

        $this->collections->archive($target, $request->user());

        return back()->with('status', 'Koleksi memori diarsipkan.');
    }

    public function unarchive(Request $request, int $collection): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $target = $this->memoryCollection($event, $collection);

        $this->collections->unarchive($target, $request->user());

        return back()->with('status', 'Koleksi memori dibuka dari arsip.');
    }

    public function addItem(Request $request, int $collection): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $target = $this->memoryCollection($event, $collection);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_column(MemoryItemType::options(), 'value'))],
            'item_id' => ['required', 'integer'],
        ]);

        $type = MemoryItemType::from((string) $validated['type']);

        // Resolve the contribution inside the owning event so a cross-event
        // identifier or a type/id mismatch fails closed with a 404.
        $type->modelClass()::query()
            ->where('event_id', $event->id)
            ->findOrFail((int) $validated['item_id']);

        try {
            $this->collections->addItem($target, $type, (int) $validated['item_id'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Item ditambahkan ke koleksi.');
    }

    /**
     * Attach a bulk selection of contributions to a collection.
     *
     * The UI posts the ids the couple ticked in the gallery or wishes list, so
     * nobody has to know internal identifiers. The whole batch fails closed
     * when any id belongs to another event, and items already in the
     * collection are counted as skipped instead of failing the request.
     */
    public function attach(Request $request): RedirectResponse
    {
        $event = $this->ownerEvent($request);

        $validated = $request->validate([
            'collection_id' => ['required', 'integer'],
            'type' => ['required', 'string', Rule::in(array_column(MemoryItemType::options(), 'value'))],
            'item_ids' => ['required', 'array', 'min:1', 'max:100'],
            'item_ids.*' => ['required', 'integer'],
        ]);

        $target = $this->memoryCollection($event, (int) $validated['collection_id']);
        $type = MemoryItemType::from((string) $validated['type']);

        $itemIds = array_values(array_unique(array_map('intval', $validated['item_ids'])));

        // Every referenced contribution must live in this event. A single
        // foreign id aborts the batch so a mixed selection never applies
        // partially.
        $found = $type->modelClass()::query()
            ->where('event_id', $event->id)
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->all();

        abort_if(count($found) !== count($itemIds), 404);

        $existing = $target->items()
            ->where('item_type', $type->value)
            ->whereIn('item_id', $itemIds)
            ->pluck('item_id')
            ->all();

        $pending = array_values(array_diff($itemIds, array_map('intval', $existing)));
        $maxItems = (int) config('memory.collection_max_items', 500);

        if ($target->items()->count() + count($pending) > $maxItems) {
            return back()->withErrors([
                'item' => 'Koleksi ini akan melebihi batas maksimal '.$maxItems.' item. Kurangi pilihan Anda.',
            ]);
        }

        try {
            foreach ($pending as $itemId) {
                $this->collections->addItem($target, $type, $itemId, $request->user());
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $added = count($pending);
        $skipped = count($itemIds) - $added;

        if ($added === 0) {
            return back()->with('status', 'Semua item yang dipilih sudah ada di koleksi ini.');
        }

        $message = $added.' item ditambahkan ke koleksi.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' item sudah ada sebelumnya.';
        }

        return back()->with('status', $message);
    }

    public function removeItem(Request $request, int $collection, string $type, int $item): RedirectResponse
    {
        $event = $this->ownerEvent($request);
        $target = $this->memoryCollection($event, $collection);

        $itemType = MemoryItemType::tryFrom($type);
        abort_if($itemType === null, 404);

        try {
            $this->collections->removeItem($target, $itemType, $item, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('status', 'Item dihapus dari koleksi.');
    }

    protected function memoryCollection(Event $event, int $id): MemoryCollection
    {
        return MemoryCollection::query()->where('event_id', $event->id)->findOrFail($id);
    }
}
