<?php

namespace App\Services;

use App\Enums\MediaStatus;
use App\Enums\MemoryExportStatus;
use App\Enums\MemoryItemType;
use App\Enums\MessageStatus;
use App\Jobs\GenerateMemoryExport;
use App\Models\Event;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\MemoryCollection;
use App\Models\MemoryCollectionItem;
use App\Models\MemoryExportJob;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Background export of an event's curated memory archive.
 *
 * The item set is snapshotted when the export is requested so a queued job
 * never picks up contributions approved after the request. Files are streamed
 * straight from their source disk into the archive, never buffered in memory.
 */
class MemoryExportService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * Capture a snapshot and queue the archive build.
     *
     * @param  array{collection_id?: int|null, include_photos?: bool|null, include_messages?: bool|null}  $filters
     */
    public function request(Event $event, User $actor, array $filters = []): MemoryExportJob
    {
        $includePhotos = (bool) ($filters['include_photos'] ?? true);
        $includeMessages = (bool) ($filters['include_messages'] ?? true);
        $collectionId = ! empty($filters['collection_id']) ? (int) $filters['collection_id'] : null;

        $mediaIds = $includePhotos ? $this->snapshotMediaIds($event, $collectionId) : [];
        $messageIds = $includeMessages ? $this->snapshotMessageIds($event, $collectionId) : [];

        $itemCount = count($mediaIds) + count($messageIds);
        $maxItems = (int) config('memory.export_max_items', 2000);

        if ($itemCount > $maxItems) {
            throw ValidationException::withMessages([
                'export' => 'Ekspor melebihi batas maksimal '.$maxItems.' item. Persempit ekspor Anda sebelum mencoba lagi.',
            ]);
        }

        $scope = [
            'event_id' => $event->id,
            'collection_id' => $collectionId,
            'include_photos' => $includePhotos,
            'include_messages' => $includeMessages,
            'media_asset_ids' => $mediaIds,
            'guestbook_message_ids' => $messageIds,
            'collection_names' => MemoryCollection::query()
                ->where('event_id', $event->id)
                ->pluck('name')
                ->all(),
        ];

        $job = MemoryExportJob::create([
            'event_id' => $event->id,
            'requested_by' => $actor->id,
            'status' => MemoryExportStatus::Queued,
            'scope' => $scope,
            'item_count' => $itemCount,
            'expires_at' => null,
        ]);

        $this->audit->record(
            'memory.export.requested',
            "Ekspor memori diminta untuk {$event->name}",
            $event,
            $actor,
            $job,
            ['job_id' => $job->id, 'item_count' => $itemCount],
        );

        GenerateMemoryExport::dispatch($job->id);

        return $job;
    }

    /**
     * Build the archive for a queued job.
     */
    public function build(MemoryExportJob $job): MemoryExportJob
    {
        $job->update([
            'status' => MemoryExportStatus::Running,
            'started_at' => now(),
        ]);

        $disk = (string) config('media.private_disk', 'local');
        $path = "exports/memory/{$job->event_id}/{$job->id}.zip";

        try {
            $itemCount = $this->writeArchive($job, $disk, $path);

            $job->update([
                'status' => MemoryExportStatus::Completed,
                'file_disk' => $disk,
                'file_path' => $path,
                'file_size' => Storage::disk($disk)->size($path),
                'item_count' => $itemCount,
                'completed_at' => now(),
                'expires_at' => now()->addHours((int) config('memory.export_ttl_hours', 48)),
            ]);

            $this->audit->record(
                'memory.export.completed',
                "Ekspor memori selesai: {$path}",
                $job->event,
                $job->requester,
                $job,
                ['file_path' => $path, 'item_count' => $itemCount],
            );

            return $job->fresh();
        } catch (Throwable $e) {
            $error = substr(get_class($e).': '.$e->getMessage(), 0, 1000);

            $job->update([
                'status' => MemoryExportStatus::Failed,
                'error' => $error,
                'completed_at' => now(),
            ]);

            $this->audit->record(
                'memory.export.failed',
                "Ekspor memori gagal: {$job->event->name}",
                $job->event,
                $job->requester,
                $job,
                ['error' => $error],
            );

            throw $e;
        }
    }

    /**
     * Remove archives past their retention window and mark the jobs expired.
     * Safe to run repeatedly because only completed jobs are selected.
     */
    public function cleanupExpired(): int
    {
        $expired = MemoryExportJob::query()
            ->where('status', MemoryExportStatus::Completed->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $cleaned = 0;

        foreach ($expired as $job) {
            if ($job->file_disk !== null && $job->file_path !== null) {
                Storage::disk($job->file_disk)->delete($job->file_path);
            }

            $job->update(['status' => MemoryExportStatus::Expired->value]);

            $cleaned++;
        }

        return $cleaned;
    }

    /**
     * @return array<int,int>
     */
    protected function snapshotMediaIds(Event $event, ?int $collectionId): array
    {
        $query = MediaAsset::query()
            ->where('event_id', $event->id)
            ->where('status', MediaStatus::Approved->value)
            ->whereNull('hidden_at')
            ->whereNull('deleted_at');

        if ($collectionId !== null) {
            $query->whereIn('id', function ($subQuery) use ($event, $collectionId): void {
                $subQuery->select('item_id')
                    ->from('memory_collection_items')
                    ->where('memory_collection_id', $collectionId)
                    ->where('event_id', $event->id)
                    ->where('item_type', MemoryItemType::MediaAsset->value);
            });
        }

        return $query->pluck('id')->all();
    }

    /**
     * @return array<int,int>
     */
    protected function snapshotMessageIds(Event $event, ?int $collectionId): array
    {
        $query = GuestbookMessage::query()
            ->where('event_id', $event->id)
            ->where('status', MessageStatus::Approved->value)
            ->whereNull('deleted_at');

        if ($collectionId !== null) {
            $query->whereIn('id', function ($subQuery) use ($event, $collectionId): void {
                $subQuery->select('item_id')
                    ->from('memory_collection_items')
                    ->where('memory_collection_id', $collectionId)
                    ->where('event_id', $event->id)
                    ->where('item_type', MemoryItemType::GuestbookMessage->value);
            });
        }

        return $query->pluck('id')->all();
    }

    /**
     * Write the archive to a temp file, then stream it onto the private disk.
     *
     * @return int Number of items written to the manifest.
     */
    protected function writeArchive(MemoryExportJob $job, string $disk, string $path): int
    {
        $scope = $job->scope;
        $event = $job->event;

        $mediaIds = array_values(array_map('intval', $scope['media_asset_ids'] ?? []));
        $messageIds = array_values(array_map('intval', $scope['guestbook_message_ids'] ?? []));

        $assets = MediaAsset::query()
            ->with(['consent', 'guestPartyMembership', 'guest', 'uploader'])
            ->whereIn('id', $mediaIds)
            ->get()
            ->keyBy('id');
        $messages = GuestbookMessage::query()->whereIn('id', $messageIds)->get()->keyBy('id');
        $collections = MemoryCollection::query()->where('event_id', $event->id)->get()->keyBy('id');

        [$itemCollections, $collectionItems] = $this->collectionMembership($event, $mediaIds, $messageIds);

        $manifestItems = [];
        $missing = [];
        $csvLines = [];

        $temporaryPath = $this->temporaryArchivePath();
        $zip = new ZipArchive;

        $opened = $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            $this->removeTemporaryFile($temporaryPath);

            throw new RuntimeException("Gagal membuat arsip ekspor sementara (kode {$opened}).");
        }

        try {
            try {
                foreach ($mediaIds as $assetId) {
                    $asset = $assets->get($assetId);

                    if ($asset === null) {
                        $missing[] = ['type' => MemoryItemType::MediaAsset->value, 'id' => $assetId, 'reason' => 'record missing'];

                        continue;
                    }

                    $sourceDisk = $asset->storageDisk();

                    if (! Storage::disk($sourceDisk)->exists($asset->path)) {
                        $missing[] = ['type' => MemoryItemType::MediaAsset->value, 'id' => $assetId, 'reason' => 'file missing'];

                        continue;
                    }

                    $entry = "photos/{$assetId}-".(Str::slug((string) $asset->original_name) ?: 'photo').'.'.$this->extensionFor($asset);

                    $zip->addFile(Storage::disk($sourceDisk)->path($asset->path), $entry);

                    $manifestItems[] = $this->manifestItem(
                        MemoryItemType::MediaAsset,
                        $assetId,
                        $asset->created_at,
                        $asset->caption,
                        $this->contributorFor($asset),
                        $itemCollections,
                    );
                }

                foreach ($messageIds as $messageId) {
                    $message = $messages->get($messageId);

                    if ($message === null) {
                        $missing[] = ['type' => MemoryItemType::GuestbookMessage->value, 'id' => $messageId, 'reason' => 'record missing'];

                        continue;
                    }

                    $manifestItems[] = $this->manifestItem(
                        MemoryItemType::GuestbookMessage,
                        $messageId,
                        $message->created_at,
                        null,
                        $message->sender_name,
                        $itemCollections,
                    );

                    $csvLines[] = [
                        $message->id,
                        $message->created_at->toIso8601String(),
                        $message->sender_name,
                        $message->message,
                        $message->visibility?->value,
                        $message->status?->value,
                    ];
                }

                $zip->addFromString('messages.csv', "\xEF\xBB\xBF".$this->renderCsv($csvLines));
                $zip->addFromString('collections.json', $this->renderJson($this->collectionsPayload($collections, $collectionItems)));
                $zip->addFromString('manifest.json', $this->renderJson([
                    'event' => $event->name,
                    'couple' => $event->coupleNames(),
                    'generated_at' => now()->toIso8601String(),
                    'timezone' => $event->timezoneName(),
                    'counts' => [
                        'photos' => count(array_filter($manifestItems, fn (array $item): bool => $item['type'] === MemoryItemType::MediaAsset->value)),
                        'messages' => count(array_filter($manifestItems, fn (array $item): bool => $item['type'] === MemoryItemType::GuestbookMessage->value)),
                        'missing' => count($missing),
                        'total' => count($manifestItems),
                    ],
                    'snapshot_semantics' => 'Snapshot diambil saat permintaan ekspor; kontribusi yang disetujui setelah permintaan tidak disertakan.',
                    'items' => $manifestItems,
                    'missing' => $missing,
                ]));
            } finally {
                $zip->close();
            }

            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Gagal membuka arsip ekspor sementara.');
            }

            try {
                Storage::disk($disk)->writeStream($path, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            $this->removeTemporaryFile($temporaryPath);
        }

        return count($manifestItems);
    }

    /**
     * Preload which collections each snapshot item belongs to so the manifest
     * and collections.json can be built without per-item queries.
     *
     * @param  array<int,int>  $mediaIds
     * @param  array<int,int>  $messageIds
     * @return array{0: array<string,array<int,string>>, 1: array<int,array<int,array{type:string,id:int}>>}
     */
    protected function collectionMembership(Event $event, array $mediaIds, array $messageIds): array
    {
        $memberships = MemoryCollectionItem::query()
            ->where('event_id', $event->id)
            ->where(function ($query) use ($mediaIds, $messageIds): void {
                $query->where(function ($query) use ($mediaIds): void {
                    $query->where('item_type', MemoryItemType::MediaAsset->value)->whereIn('item_id', $mediaIds);
                })->orWhere(function ($query) use ($messageIds): void {
                    $query->where('item_type', MemoryItemType::GuestbookMessage->value)->whereIn('item_id', $messageIds);
                });
            })
            ->get();

        $names = MemoryCollection::query()->where('event_id', $event->id)->pluck('name', 'id');

        $itemCollections = [];
        $collectionItems = [];

        foreach ($memberships as $membership) {
            $name = $names->get($membership->memory_collection_id);

            if ($name === null) {
                continue;
            }

            $key = $membership->item_type.':'.$membership->item_id;
            $itemCollections[$key][] = $name;
            $collectionItems[$membership->memory_collection_id][] = [
                'type' => $membership->item_type,
                'id' => (int) $membership->item_id,
            ];
        }

        return [$itemCollections, $collectionItems];
    }

    /**
     * @param  array<string,array<int,string>>  $itemCollections
     * @return array<string,mixed>
     */
    protected function manifestItem(
        MemoryItemType $type,
        int $id,
        DateTimeInterface $submittedAt,
        ?string $caption,
        ?string $contributor,
        array $itemCollections,
    ): array {
        return [
            'type' => $type->value,
            'id' => $id,
            'submitted_at' => $submittedAt->format(DATE_ATOM),
            'caption' => $caption,
            'contributor' => $contributor,
            'collections' => array_values(array_unique($itemCollections[$type->value.':'.$id] ?? [])),
        ];
    }

    /**
     * @param  Collection<int,MemoryCollection>  $collections
     * @param  array<int,array<int,array{type:string,id:int}>>  $collectionItems
     * @return array<int,array{name:string,description:?string,item_ids:array<int,array{type:string,id:int}>}>
     */
    protected function collectionsPayload(Collection $collections, array $collectionItems): array
    {
        $payload = [];

        foreach ($collections as $collection) {
            $payload[] = [
                'name' => $collection->name,
                'description' => $collection->description,
                'item_ids' => $collectionItems[$collection->id] ?? [],
            ];
        }

        return $payload;
    }

    /**
     * @param  array<int,array<int,mixed>>  $lines
     */
    protected function renderCsv(array $lines): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['id', 'submitted_at', 'sender_name', 'message', 'visibility', 'status']);

        foreach ($lines as $line) {
            fputcsv($handle, $line);
        }

        rewind($handle);

        $csv = (string) stream_get_contents($handle);

        fclose($handle);

        return $csv;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    protected function renderJson(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function contributorFor(MediaAsset $asset): ?string
    {
        // The display name the contributor typed is the most accurate label;
        // fall back to the verified party record only when no consent exists.
        return $asset->consent?->contributor_name
            ?? $asset->guestPartyMembership?->name
            ?? $asset->guest?->name
            ?? $asset->uploader?->name;
    }

    protected function extensionFor(MediaAsset $asset): string
    {
        $extension = Str::lower((string) pathinfo((string) $asset->path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'jpg';
    }

    protected function temporaryArchivePath(): string
    {
        // Not tempnam(): that creates the file immediately, which would leave an
        // orphan behind once the ".zip" suffix is appended. ZipArchive::CREATE
        // creates the archive itself.
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'memory-export-'.Str::random(16).'.zip';
    }

    protected function removeTemporaryFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
