<?php

namespace App\Services;

use App\Enums\MemoryItemType;
use App\Models\Event;
use App\Models\MemoryCollection;
use App\Models\MemoryCollectionItem;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Curated, ordered groupings of memory contributions.
 *
 * Collections are never soft-deleted: archiving hides them from guests while
 * keeping the curation history intact. Hard deletion is reserved for the
 * explicit destroy action and never touches the contributions themselves.
 */
class MemoryCollectionService
{
    public function __construct(protected AuditService $audit) {}

    public function create(Event $event, array $data, User $actor): MemoryCollection
    {
        $name = trim((string) ($data['name'] ?? ''));

        $this->assertNameAvailable($event, $name);

        return DB::transaction(function () use ($event, $data, $actor, $name): MemoryCollection {
            $collection = MemoryCollection::create([
                'event_id' => $event->id,
                'name' => $name,
                'description' => $data['description'] ?? null,
                'is_visible_to_guests' => (bool) ($data['is_visible_to_guests'] ?? false),
                'sort_order' => $data['sort_order'] ?? 0,
                'created_by' => $actor->id,
            ]);

            $this->audit->record(
                'memory.collection.created',
                "Koleksi memori {$collection->name} dibuat",
                $event,
                $actor,
                $collection,
            );

            return $collection->fresh();
        });
    }

    public function update(MemoryCollection $collection, array $data, User $actor): MemoryCollection
    {
        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $collection->name;

        $this->assertNameAvailable($collection->event, $name, $collection->id);

        return DB::transaction(function () use ($collection, $data, $name, $actor): MemoryCollection {
            $collection->update(array_merge($data, ['name' => $name]));

            $this->audit->record(
                'memory.collection.updated',
                "Koleksi memori {$collection->name} diperbarui",
                $collection->event,
                $actor,
                $collection,
            );

            return $collection->fresh();
        });
    }

    /**
     * Archiving is reversible and never removes the collection or its items.
     */
    public function archive(MemoryCollection $collection, User $actor): MemoryCollection
    {
        return DB::transaction(function () use ($collection, $actor): MemoryCollection {
            $collection->update(['archived_at' => now()]);

            $this->audit->record(
                'memory.collection.archived',
                "Koleksi memori {$collection->name} diarsipkan",
                $collection->event,
                $actor,
                $collection,
            );

            return $collection->fresh();
        });
    }

    /**
     * Restoring an archived collection re-validates the name against the
     * collections that are still active.
     */
    public function unarchive(MemoryCollection $collection, User $actor): MemoryCollection
    {
        return DB::transaction(function () use ($collection, $actor): MemoryCollection {
            $this->assertNameAvailable($collection->event, $collection->name, $collection->id);

            $collection->update(['archived_at' => null]);

            $this->audit->record(
                'memory.collection.unarchived',
                "Koleksi memori {$collection->name} dibuka dari arsip",
                $collection->event,
                $actor,
                $collection,
            );

            return $collection->fresh();
        });
    }

    /**
     * Hard delete. The database cascade removes the pivot rows; the referenced
     * media assets and guestbook messages are never touched.
     */
    public function destroy(MemoryCollection $collection, User $actor): void
    {
        DB::transaction(function () use ($collection, $actor): void {
            $this->audit->record(
                'memory.collection.deleted',
                "Koleksi memori {$collection->name} dihapus permanen",
                $collection->event,
                $actor,
                $collection,
            );

            $collection->delete();
        });
    }

    /**
     * Attach an existing contribution to the collection. The target is always
     * resolved inside the owning event so cross-event identifiers 404.
     */
    public function addItem(MemoryCollection $collection, MemoryItemType $type, int $itemId, User $actor): MemoryCollectionItem
    {
        $class = $type->modelClass();
        $class::query()->where('event_id', $collection->event_id)->findOrFail($itemId);

        return DB::transaction(function () use ($collection, $type, $itemId, $actor): MemoryCollectionItem {
            $maxItems = (int) config('memory.collection_max_items', 500);

            if ($collection->items()->count() >= $maxItems) {
                throw ValidationException::withMessages([
                    'item' => 'Koleksi ini sudah mencapai batas maksimal item.',
                ]);
            }

            $pivot = MemoryCollectionItem::firstOrCreate(
                [
                    'memory_collection_id' => $collection->id,
                    'item_type' => $type->value,
                    'item_id' => $itemId,
                ],
                [
                    'event_id' => $collection->event_id,
                    'sort_order' => $collection->items()->max('sort_order') + 1,
                    'added_by' => $actor->id,
                ],
            );

            $this->audit->record(
                'memory.collection.item_added',
                "Item {$type->label()} #{$itemId} ditambahkan ke koleksi {$collection->name}",
                $collection->event,
                $actor,
                $pivot,
                ['item_type' => $type->value, 'item_id' => $itemId],
            );

            return $pivot->fresh();
        });
    }

    /**
     * Detach a single contribution. Only the pivot row is removed.
     */
    public function removeItem(MemoryCollection $collection, MemoryItemType $type, int $itemId, User $actor): void
    {
        DB::transaction(function () use ($collection, $type, $itemId, $actor): void {
            $pivot = MemoryCollectionItem::query()
                ->where('memory_collection_id', $collection->id)
                ->where('item_type', $type->value)
                ->where('item_id', $itemId)
                ->first();

            if ($pivot === null) {
                throw ValidationException::withMessages([
                    'item' => 'Item tersebut tidak ada dalam koleksi ini.',
                ]);
            }

            $pivot->delete();

            $this->audit->record(
                'memory.collection.item_removed',
                "Item {$type->label()} #{$itemId} dihapus dari koleksi {$collection->name}",
                $collection->event,
                $actor,
                $pivot,
                ['item_type' => $type->value, 'item_id' => $itemId],
            );
        });
    }

    /**
     * Reorder by an explicit list of pivot ids. Identifiers that belong to
     * another collection are ignored.
     */
    public function reorder(MemoryCollection $collection, array $orderedIds, User $actor): void
    {
        DB::transaction(function () use ($collection, $orderedIds, $actor): void {
            $items = MemoryCollectionItem::query()
                ->where('memory_collection_id', $collection->id)
                ->whereIn('id', $orderedIds)
                ->get()
                ->keyBy('id');

            foreach ($orderedIds as $index => $id) {
                $item = $items->get((int) $id);

                if ($item === null) {
                    continue;
                }

                $item->update(['sort_order' => $index]);
            }

            $this->audit->record(
                'memory.collection.reordered',
                "Urutan koleksi {$collection->name} diperbarui",
                $collection->event,
                $actor,
                $collection,
                ['item_ids' => array_values(array_map('intval', $orderedIds))],
            );
        });
    }

    /**
     * Paginate the pivot rows, then hydrate the referenced models in one query
     * per item type so large collections stay free of N+1 queries.
     */
    public function itemsFor(MemoryCollection $collection, int $perPage = 48): LengthAwarePaginator
    {
        $paginator = $collection->items()->paginate($perPage);

        $this->hydrateItems($paginator);

        return $paginator;
    }

    protected function hydrateItems(LengthAwarePaginator $paginator): void
    {
        $rows = collect($paginator->items());

        foreach ($rows->groupBy('item_type') as $type => $group) {
            $itemType = MemoryItemType::tryFrom((string) $type);

            if ($itemType === null) {
                continue;
            }

            $class = $itemType->modelClass();

            $models = $class::query()
                ->whereIn('id', $group->pluck('item_id')->all())
                ->get()
                ->keyBy('id');

            foreach ($group as $row) {
                $row->setRelation('item', $models->get((int) $row->item_id));
            }
        }
    }

    /**
     * Names are unique per event among active collections only; archived
     * collections keep their name available for reuse.
     */
    protected function assertNameAvailable(Event $event, string $name, ?int $ignoreId = null): void
    {
        $exists = MemoryCollection::query()
            ->where('event_id', $event->id)
            ->whereNull('archived_at')
            ->where('name', $name)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Koleksi dengan nama yang sama sudah ada pada acara ini.',
            ]);
        }
    }
}
