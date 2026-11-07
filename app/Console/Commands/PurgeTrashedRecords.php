<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeTrashedRecords extends Command
{
    protected $signature = 'vowspace:purge-trashed
                            {--days= : Retention window in days (defaults to config)}
                            {--dry-run : Report what would be purged without deleting}
                            {--include-events : Also permanently delete trashed events}';

    protected $description = 'Permanently purge soft-deleted records past the retention window, including media files';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('vowspace.retention.trashed_days', 30));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $media = MediaAsset::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $guests = Guest::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $messages = GuestbookMessage::onlyTrashed()->where('deleted_at', '<', $cutoff);
        $events = Event::onlyTrashed()->where('deleted_at', '<', $cutoff);

        $this->info("Retention: {$days} days (cutoff {$cutoff->toDateTimeString()})".($dryRun ? ' [dry-run]' : ''));

        $mediaCount = (clone $media)->count();
        $guestCount = (clone $guests)->count();
        $messageCount = (clone $messages)->count();
        $eventCount = (clone $events)->count();

        $this->line("Media assets: {$mediaCount}");
        $this->line("Guests: {$guestCount}");
        $this->line("Guestbook messages: {$messageCount}");
        $this->line('Events: '.($this->option('include-events') ? $eventCount : 'skipped (use --include-events)'));

        if ($dryRun) {
            return self::SUCCESS;
        }

        $publicDisk = config('media.public_disk', 'public');
        $stagingDisk = config('media.staging_disk', 'local');

        (clone $media)->chunkById(100, function ($assets) use ($publicDisk, $stagingDisk): void {
            foreach ($assets as $asset) {
                // Delete original from recorded disk and staging
                Storage::disk($asset->storageDisk())->delete($asset->path);
                if (Storage::disk($stagingDisk)->exists($asset->path)) {
                    Storage::disk($stagingDisk)->delete($asset->path);
                }

                // Delete public derivatives
                if ($asset->thumbnail_path && $asset->thumbnail_path !== $asset->path) {
                    Storage::disk($publicDisk)->delete($asset->thumbnail_path);
                }
                if ($asset->medium_path && $asset->medium_path !== $asset->path) {
                    Storage::disk($publicDisk)->delete($asset->medium_path);
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
            (clone $events)->with('giftAccounts')->chunkById(50, function ($events): void {
                foreach ($events as $event) {
                    if ($event->cover_image_path) {
                        Storage::disk('public')->delete($event->cover_image_path);
                    }
                    foreach ($event->giftAccounts as $gift) {
                        if ($gift->qris_image_path) {
                            Storage::disk('public')->delete($gift->qris_image_path);
                        }
                    }
                    $event->forceDelete();
                }
            });
        }

        Log::info('vowspace:purge-trashed completed', [
            'media' => $mediaCount,
            'guests' => $guestCount,
            'messages' => $messageCount,
            'events' => $this->option('include-events') ? $eventCount : 0,
        ]);

        $this->info('Purge complete.');

        return self::SUCCESS;
    }
}
