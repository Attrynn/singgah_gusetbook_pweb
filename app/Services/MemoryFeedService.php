<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\MemoryItemType;
use App\Enums\MemoryState;
use App\Enums\MessageStatus;
use App\Models\Event;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\MemoryCollection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Read models for the event memory feed.
 *
 * The timeline spans two tables, so it is built as a UNION ALL query to keep
 * pagination totals correct across both contribution types. Ordering is
 * strictly by submission time; no capture time is ever invented.
 */
class MemoryFeedService
{
    /**
     * Approved contributions, newest submission first.
     *
     * @param  array{collection_id?: int|null, from?: string|null, to?: string|null, type?: string|null}  $filters
     */
    public function timeline(Event $event, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $paginator = $this->feedQuery($event, $filters, 'desc')->paginate($perPage);

        return $this->toFeedRows($paginator, 'id');
    }

    /**
     * Pending review queue, oldest submission first so the oldest backlog
     * surfaces on top.
     *
     * @param  array{collection_id?: int|null, from?: string|null, to?: string|null, type?: string|null}  $filters
     */
    public function pending(Event $event, array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $paginator = $this->feedQuery($event, $filters, 'asc', true)->paginate($perPage);

        return $this->toFeedRows($paginator, 'id');
    }

    /**
     * Aggregate counters for the memory dashboard.
     */
    public function stats(Event $event): array
    {
        $media = MediaAsset::query()->where('event_id', $event->id);
        $messages = GuestbookMessage::query()->where('event_id', $event->id);

        $cutoff = now()->subDays(7);

        return [
            'photos_total' => $media->count(),
            'photos_pending' => (clone $media)->where('status', MediaStatus::Pending->value)->count(),
            'messages_total' => $messages->count(),
            'messages_pending' => (clone $messages)->where('status', MessageStatus::Pending->value)->count(),
            'hidden_total' => (clone $media)->whereNotNull('hidden_at')->count()
                + (clone $messages)->where('status', MessageStatus::Hidden->value)->count(),
            'collections_total' => MemoryCollection::query()->where('event_id', $event->id)->count(),
            'contributions_last_7_days' => (clone $media)->where('created_at', '>=', $cutoff)->count()
                + (clone $messages)->where('created_at', '>=', $cutoff)->count(),
        ];
    }

    /**
     * One collection's curated items in collection order, shaped like the
     * timeline rows.
     */
    public function forCollection(Event $event, MemoryCollection $collection, int $perPage = 48): LengthAwarePaginator
    {
        abort_unless((int) $collection->event_id === (int) $event->id, 404);

        $paginator = $collection->items()->paginate($perPage);

        return $this->toFeedRows($paginator, 'item_id');
    }

    /**
     * Build the UNION ALL feed query. Filters are applied to each branch
     * before the union so they stay valid for both tables.
     */
    protected function feedQuery(Event $event, array $filters, string $direction, bool $pending = false): Builder
    {
        $type = $filters['type'] ?? null;

        if ($type !== null && MemoryItemType::tryFrom((string) $type) === null) {
            throw ValidationException::withMessages(['type' => 'Tipe kontribusi memori tidak dikenal.']);
        }

        $branches = [];

        if ($type === null || $type === MemoryItemType::MediaAsset->value) {
            $branches[] = $this->mediaBranch($event, $filters, $pending);
        }

        if ($type === null || $type === MemoryItemType::GuestbookMessage->value) {
            $branches[] = $this->messageBranch($event, $filters, $pending);
        }

        $query = array_shift($branches);

        foreach ($branches as $branch) {
            $query->unionAll($branch);
        }

        return $query->orderBy('created_at', $direction);
    }

    protected function mediaBranch(Event $event, array $filters, bool $pending): Builder
    {
        $query = DB::table('media_assets')
            ->select('id', 'created_at', DB::raw("'media_asset' as item_type"))
            ->where('event_id', $event->id)
            ->where('status', ($pending ? MediaStatus::Pending : MediaStatus::Approved)->value)
            ->whereNull('deleted_at');

        if (! $pending) {
            $query->whereNull('hidden_at');
        }

        return $this->applyFeedFilters($query, $event, $filters, MemoryItemType::MediaAsset);
    }

    protected function messageBranch(Event $event, array $filters, bool $pending): Builder
    {
        $query = DB::table('guestbook_messages')
            ->select('id', 'created_at', DB::raw("'guestbook_message' as item_type"))
            ->where('event_id', $event->id)
            ->where('status', ($pending ? MessageStatus::Pending : MessageStatus::Approved)->value)
            ->whereNull('deleted_at');

        return $this->applyFeedFilters($query, $event, $filters, MemoryItemType::GuestbookMessage);
    }

    protected function applyFeedFilters(Builder $query, Event $event, array $filters, MemoryItemType $type): Builder
    {
        $collectionId = ! empty($filters['collection_id']) ? (int) $filters['collection_id'] : null;

        if ($collectionId !== null) {
            $query->whereIn('id', function (Builder $subQuery) use ($collectionId, $event, $type): void {
                $subQuery->select('item_id')
                    ->from('memory_collection_items')
                    ->where('memory_collection_id', $collectionId)
                    ->where('event_id', $event->id)
                    ->where('item_type', $type->value);
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * Hydrate the models behind the current page in one query per type, then
     * project every row into the feed shape.
     *
     * @param  string  $idColumn  Pivot key holding the contribution id.
     */
    protected function toFeedRows(LengthAwarePaginator $paginator, string $idColumn = 'id'): LengthAwarePaginator
    {
        $rows = collect($paginator->items());

        $mediaIds = $rows->where('item_type', MemoryItemType::MediaAsset->value)->pluck($idColumn)->all();
        $messageIds = $rows->where('item_type', MemoryItemType::GuestbookMessage->value)->pluck($idColumn)->all();

        $assets = $mediaIds === []
            ? collect()
            : MediaAsset::query()
                ->with(['consent', 'guest', 'guestPartyMembership', 'uploader'])
                ->whereIn('id', $mediaIds)
                ->get()
                ->keyBy('id');

        $messages = $messageIds === []
            ? collect()
            : GuestbookMessage::query()->whereIn('id', $messageIds)->get()->keyBy('id');

        $items = $rows->map(function (mixed $row) use ($assets, $messages, $idColumn): ?array {
            $type = MemoryItemType::tryFrom((string) $row->item_type);

            if ($type === null) {
                return null;
            }

            $model = $type === MemoryItemType::MediaAsset
                ? $assets->get((int) $row->{$idColumn})
                : $messages->get((int) $row->{$idColumn});

            if ($model === null) {
                return null;
            }

            return $this->feedRow($type, $model);
        })->filter();

        return new LengthAwarePaginator(
            $items->values(),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => $paginator->path()],
        );
    }

    protected function feedRow(MemoryItemType $type, Model $model): array
    {
        return [
            'type' => $type,
            'state' => $this->resolveState($model),
            'at' => CarbonImmutable::instance($model->created_at),
            'model' => $model,
            'caption' => $model instanceof MediaAsset ? $model->caption : null,
            'contributor' => $model instanceof MediaAsset ? $this->contributorFor($model) : $model->sender_name,
        ];
    }

    protected function resolveState(Model $model): MemoryState
    {
        if ($model instanceof MediaAsset) {
            if ($model->hidden_at !== null) {
                return MemoryState::Hidden;
            }

            return match ($model->status) {
                MediaStatus::Approved => MemoryState::Approved,
                MediaStatus::Rejected => MemoryState::Rejected,
                default => MemoryState::Pending,
            };
        }

        return match ($model->status) {
            MessageStatus::Approved => MemoryState::Approved,
            MessageStatus::Rejected => MemoryState::Rejected,
            MessageStatus::Hidden => MemoryState::Hidden,
            default => MemoryState::Pending,
        };
    }

    protected function contributorFor(MediaAsset $asset): ?string
    {
        // Prefer the display name the contributor typed; the party record is
        // only a fallback for contributions without a consent row.
        return $asset->consent?->contributor_name
            ?? $asset->guestPartyMembership?->name
            ?? $asset->guest?->name
            ?? $asset->uploader?->name;
    }
}
