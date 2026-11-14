<?php

namespace App\Console\Commands;

use App\Enums\MemoryExportStatus;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\MediaAlbum;
use App\Models\MediaAsset;
use App\Models\MemoryExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeTrashedRecords extends Command
{
    protected $signature = 'vowspace:purge-trashed
                            {--days= : Retention window in days (defaults to config)}
                            {--dry-run : Report what would be purged without deleting}
                            {--include-events : Also permanently delete trashed events}';

    protected $description = 'Permanently purge soft-deleted records past the retention window, including every media object';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('vowspace.retention.trashed_days', 30));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $media = MediaAsset::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $guests = Guest::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $messages = GuestbookMessage::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $events = Event::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $exportJobs = MemoryExportJob::query()
            ->where(fn ($query) => $query->whereNotNull('expires_at')->where('expires_at', '<', now()))
            ->orWhere(fn ($query) => $query->where('status', MemoryExportStatus::Failed->value)->where('created_at', '<', $cutoff));

        $this->info("Retention: {$days} days (cutoff {$cutoff->toDateTimeString()})".($dryRun ? ' [dry-run]' : ''));

        $mediaCount = (clone $media)->count();
        $guestCount = (clone $guests)->count();
        $messageCount = (clone $messages)->count();
        $eventCount = (clone $events)->count();
        $exportJobCount = (clone $exportJobs)->count();

        $this->line("Media assets: {$mediaCount}");
        $this->line("Guests: {$guestCount}");
        $this->line("Guestbook messages: {$messageCount}");
        $this->line('Events: '.($this->option('include-events') ? $eventCount : 'skipped (use --include-events)'));
        $this->line("Memory export jobs: {$exportJobCount}");

        if ($dryRun) {
            return self::SUCCESS;
        }

        $failures = 0;

        (clone $media)->chunkById(100, function ($assets) use (&$failures): void {
            foreach ($assets as $asset) {
                if (! $this->purgeMediaAsset($asset)) {
                    $failures++;

                    continue;
                }

                $asset->forceDelete();
            }
        });

        (clone $guests)->chunkById(100, function ($guests): void {
            foreach ($guests as $guest) {
                $guest->tokens()->update(['revoked_at' => now()]);
                $guest->forceDelete();
            }
        });

        (clone $messages)->chunkById(100, function ($messages): void {
            foreach ($messages as $message) {
                $message->forceDelete();
            }
        });

        if ($this->option('include-events')) {
            (clone $events)->chunkById(50, function ($events) use (&$failures): void {
                foreach ($events as $event) {
                    if (! $this->purgeEvent($event)) {
                        $failures++;

                        continue;
                    }

                    $event->forceDelete();
                }
            });
        }

        (clone $exportJobs)->chunkById(100, function ($jobs) use (&$failures): void {
            foreach ($jobs as $job) {
                if (! $this->purgeMemoryExportJob($job)) {
                    $failures++;

                    continue;
                }
            }
        });

        $anonymised = $this->anonymiseAuditIdentifiers();

        Log::info('vowspace:purge-trashed completed', [
            'media' => $mediaCount,
            'guests' => $guestCount,
            'messages' => $messageCount,
            'events' => $this->option('include-events') ? $eventCount : 0,
            'memory_export_jobs' => $exportJobCount,
            'audit_identifiers_anonymised' => $anonymised,
            'storage_failures' => $failures,
        ]);

        if ($failures > 0) {
            $this->error("Purge finished with {$failures} storage failure(s). The affected records were kept for retry.");

            return self::FAILURE;
        }

        $this->info('Purge complete.');

        return self::SUCCESS;
    }

    /**
     * Remove every object an asset may own (original, thumbnail, medium and
     * legacy public/staging copies). Returns false when a file still exists,
     * so the database row is preserved for a later retry.
     */
    protected function purgeMediaAsset(MediaAsset $asset): bool
    {
        $paths = array_values(array_unique(array_filter([
            $asset->path, $asset->thumbnail_path, $asset->medium_path,
        ])));

        if ($paths === []) {
            return true;
        }

        foreach ($this->mediaDisks($asset) as $disk) {
            try {
                Storage::disk($disk)->delete($paths);
            } catch (Throwable $e) {
                report($e);
                Log::error('Media purge failed', ['asset_id' => $asset->id, 'disk' => $disk]);

                return false;
            }

            foreach ($paths as $path) {
                if (Storage::disk($disk)->exists($path)) {
                    Log::error('Media object still present after purge', [
                        'asset_id' => $asset->id,
                        'disk' => $disk,
                        'path' => $path,
                    ]);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Remove the event's memory contribution directory, including objects that
     * are no longer tracked by a media asset row. Returns false when the
     * directory still exists so the event is kept for a later retry.
     */
    protected function purgeMemoryDirectory(Event $event): bool
    {
        $directory = str_replace('{event}', (string) $event->id, (string) config('memory.upload_path', 'events/{event}/memory'));

        foreach ($this->mediaDisks() as $disk) {
            if (! Storage::disk($disk)->directoryExists($directory)) {
                continue;
            }

            try {
                Storage::disk($disk)->deleteDirectory($directory);
            } catch (Throwable $e) {
                report($e);
                Log::error('Memory directory purge failed', [
                    'event_id' => $event->id,
                    'disk' => $disk,
                    'directory' => $directory,
                ]);

                return false;
            }

            if (Storage::disk($disk)->directoryExists($directory)) {
                Log::error('Memory directory still present after purge', [
                    'event_id' => $event->id,
                    'disk' => $disk,
                    'directory' => $directory,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Remove an expired or long-failed memory export archive together with its
     * job row. Returns false when the archive cannot be removed so the job is
     * kept for a later retry.
     */
    protected function purgeMemoryExportJob(MemoryExportJob $job): bool
    {
        if ($job->fileExists()) {
            try {
                Storage::disk($job->file_disk)->delete($job->file_path);
            } catch (Throwable $e) {
                report($e);
                Log::error('Memory export archive purge failed', [
                    'job_id' => $job->id,
                    'disk' => $job->file_disk,
                    'path' => $job->file_path,
                ]);

                return false;
            }

            if ($job->fileExists()) {
                Log::error('Memory export archive still present after purge', [
                    'job_id' => $job->id,
                    'disk' => $job->file_disk,
                    'path' => $job->file_path,
                ]);

                return false;
            }

            Log::info('Memory export archive purged', [
                'job_id' => $job->id,
                'disk' => $job->file_disk,
                'path' => $job->file_path,
            ]);
        }

        $job->forceDelete();

        Log::info('Memory export job purged', [
            'job_id' => $job->id,
            'status' => $job->status instanceof MemoryExportStatus ? $job->status->value : $job->status,
        ]);

        return true;
    }

    /**
     * @return array<int,string>
     */
    protected function mediaDisks(?MediaAsset $asset = null): array
    {
        return array_values(array_unique(array_filter([
            $asset?->storageDisk(),
            config('media.private_disk', 'local'),
            config('media.staging_disk', 'local'),
            config('media.public_disk', 'public'),
        ])));
    }

    /**
     * Purge every file owned by the event before the row is removed. If any
     * object cannot be deleted, the event is kept so the operation is
     * idempotent and retryable.
     */
    protected function purgeEvent(Event $event): bool
    {
        $failures = 0;

        foreach ($event->mediaAssets()->withTrashed()->get() as $asset) {
            if (! $this->purgeMediaAsset($asset)) {
                $failures++;
            }
        }

        if (! $this->purgeMemoryDirectory($event)) {
            $failures++;
        }

        if ($failures > 0) {
            Log::error('Event purge aborted: media objects remain', ['event_id' => $event->id, 'failures' => $failures]);

            return false;
        }

        // Composite cover constraint is restrictive, so detach covers first.
        MediaAlbum::where('event_id', $event->id)->update(['cover_media_asset_id' => null]);

        $publicDisk = config('media.public_disk', 'public');
        $paths = array_filter([$event->cover_image]);

        foreach ($event->giftAccounts()->get() as $gift) {
            $paths[] = $gift->qris_image_path;
        }

        $paths = array_values(array_filter($paths));

        if ($paths !== []) {
            try {
                Storage::disk($publicDisk)->delete($paths);
            } catch (Throwable $e) {
                report($e);
                Log::error('Event asset purge failed', ['event_id' => $event->id]);

                return false;
            }

            foreach ($paths as $path) {
                if (Storage::disk($publicDisk)->exists($path)) {
                    Log::error('Event asset still present after purge', ['event_id' => $event->id, 'path' => $path]);

                    return false;
                }
            }
        }

        return $failures === 0;
    }

    /**
     * Audit rows stay for accountability; network identifiers are removed
     * after the configured window. Returns the number of rows minimised.
     */
    protected function anonymiseAuditIdentifiers(): int
    {
        $days = (int) config('vowspace.retention.audit_log_ip_days', 90);

        if ($days <= 0) {
            return 0;
        }

        return AuditLog::where('created_at', '<', now()->subDays($days))
            ->where(fn ($query) => $query->whereNotNull('ip_address')->orWhereNotNull('user_agent'))
            ->update(['ip_address' => null, 'user_agent' => null]);
    }
}
