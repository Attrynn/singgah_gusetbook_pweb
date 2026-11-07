<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * VS-PRV-005: move media that was stored on the public disk before the
 * private-by-default model to private storage.
 *
 * Every object is copied, verified by hash and only then is the database
 * reference updated. The previous public object is deleted only after the
 * destination is confirmed, so an interrupted run is safe to repeat.
 */
class MigrateLegacyMedia extends Command
{
    protected $signature = 'vowspace:migrate-legacy-media
                            {--dry-run : Report what would be migrated without changing anything}
                            {--keep-source : Keep the previous public object after a verified copy}';

    protected $description = 'Copy legacy public guest media to private storage, verify it, then update references';

    public function handle(): int
    {
        $privateDisk = (string) config('media.private_disk', 'local');
        $dryRun = (bool) $this->option('dry-run');
        $failures = 0;
        $migrated = 0;

        $assets = MediaAsset::withTrashed()
            ->where(fn ($query) => $query->whereNull('disk')->orWhere('disk', '!=', $privateDisk))
            ->orderBy('id')
            ->get();

        $this->info("Legacy media candidates: {$assets->count()}".($dryRun ? ' [dry-run]' : ''));

        foreach ($assets as $asset) {
            $sourceDisk = $asset->storageDisk();

            if ($sourceDisk === $privateDisk) {
                continue;
            }

            $paths = array_values(array_unique(array_filter([
                $asset->path, $asset->thumbnail_path, $asset->medium_path,
            ])));

            $missing = [];
            $conflicts = [];

            foreach ($paths as $path) {
                if (! Storage::disk($sourceDisk)->exists($path)) {
                    $missing[] = $path;

                    continue;
                }

                if (Storage::disk($privateDisk)->exists($path)
                    && $this->hashFor($sourceDisk, $path) !== $this->hashFor($privateDisk, $path)) {
                    $conflicts[] = $path;
                }
            }

            if ($conflicts !== []) {
                $this->warn("Asset {$asset->id}: destination conflict, skipped (".implode(', ', $conflicts).')');
                $failures++;

                continue;
            }

            if ($missing !== []) {
                $this->warn("Asset {$asset->id}: missing source object(s): ".implode(', ', $missing));
            }

            $this->line("Asset {$asset->id}: {$asset->original_name} [{$sourceDisk}] -> [{$privateDisk}]");

            if ($dryRun) {
                $migrated++;

                continue;
            }

            try {
                foreach ($paths as $path) {
                    if (! Storage::disk($sourceDisk)->exists($path) || Storage::disk($privateDisk)->exists($path)) {
                        continue;
                    }

                    $stream = Storage::disk($sourceDisk)->readStream($path);

                    if ($stream === null) {
                        throw new RuntimeException("Cannot read {$path} from {$sourceDisk}.");
                    }

                    $written = Storage::disk($privateDisk)->writeStream($path, $stream);

                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    if (! $written || ! Storage::disk($privateDisk)->exists($path)) {
                        throw new RuntimeException("Cannot write {$path} to {$privateDisk}.");
                    }

                    if ($this->hashFor($sourceDisk, $path) !== $this->hashFor($privateDisk, $path)) {
                        throw new RuntimeException("Hash mismatch after copying {$path}.");
                    }
                }

                DB::transaction(fn () => $asset->forceFill(['disk' => $privateDisk])->save());

                if (! $this->option('keep-source')) {
                    Storage::disk($sourceDisk)->delete($paths);
                }

                $migrated++;
            } catch (Throwable $e) {
                report($e);
                $this->error("Asset {$asset->id} failed: {$e->getMessage()}");
                $failures++;
            }
        }

        $this->info("Migrated: {$migrated}, failures: {$failures}");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function hashFor(string $disk, string $path): string
    {
        return hash('sha256', (string) Storage::disk($disk)->get($path));
    }
}
