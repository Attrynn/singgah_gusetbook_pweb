<?php

namespace App\Jobs;

use App\Enums\MemoryExportStatus;
use App\Models\MemoryExportJob;
use App\Services\MemoryExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Builds one memory export archive.
 *
 * The job only carries the export identifier; the archive itself is assembled
 * by MemoryExportService::build() so the queue payload stays tiny.
 */
class GenerateMemoryExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [30, 120, 300];

    /**
     * The Queueable trait already owns $queue, so the dedicated queue name is
     * assigned here instead of redeclaring the property.
     */
    public function __construct(public int $exportJobId)
    {
        $this->queue = 'memory-exports';
    }

    public function handle(MemoryExportService $exports): void
    {
        $job = MemoryExportJob::find($this->exportJobId);

        if ($job === null || $job->isTerminal()) {
            return;
        }

        $exports->build($job);
    }

    public function failed(?Throwable $e): void
    {
        $job = MemoryExportJob::find($this->exportJobId);

        if ($job === null || $job->isTerminal()) {
            return;
        }

        $job->update([
            'status' => MemoryExportStatus::Failed,
            'error' => $e !== null ? substr(get_class($e).': '.$e->getMessage(), 0, 1000) : 'Unknown export failure.',
            'completed_at' => now(),
        ]);
    }
}
