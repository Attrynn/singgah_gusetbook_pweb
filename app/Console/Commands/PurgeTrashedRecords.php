<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Guest;
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
        $events = Event::onlyTrashed()->where('deleted_at', '<', $cutoff);

        $this->info("Retention: {$days} days (cutoff {$cutoff->toDateTimeString()})".($dryRun ? ' [dry-run]' : ''));

        $mediaCount = (clone $media)->count();
        $guestCount = (clone $guests)->count();
        $eventCount = (clone $events)->count();

        $this->line("Media assets: {$mediaCount}");
        $this->line("Guests: {$guestCount}");
        $this->line('Events: '.($this->option('include-events') ? $eventCount : 'skipped (use --include-events)'));

        if ($dryRun) {
            return self::SUCCESS;
        }

        (clone $media)->chunkById(100, function ($assets): void {
            foreach ($assets as $asset) {
                $paths = array_filter([$asset->path, $asset->thumbnail_path, $asset->medium_path]);
                Storage::disk($asset->storageDisk())->delete($paths);
                $asset->forceDelete();
            }
        });

        (clone $guests)->chunkById(100, function ($guests): void {
            foreach ($guests as $guest) {
                $guest->tokens()->update(['revoked_at' => now()]);
                $guest->forceDelete();
            }
        });

        if ($this->option('include-events')) {
            (clone $events)->chunkById(50, function ($events): void {
                foreach ($events as $event) {
                    $event->forceDelete();
                }
            });
        }

        Log::info('vowspace:purge-trashed completed', [
            'media' => $mediaCount,
            'guests' => $guestCount,
            'events' => $this->option('include-events') ? $eventCount : 0,
        ]);

        $this->info('Purge complete.');

        return self::SUCCESS;
    }
}
