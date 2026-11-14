<?php

namespace App\Console\Commands;

use App\Services\MemoryExportService;
use Illuminate\Console\Command;

class ExpireMemoryExports extends Command
{
    protected $signature = 'vowspace:expire-memory-exports';

    protected $description = 'Hapus arsip ekspor kenangan yang sudah kedaluwarsa dan tandai job sebagai expired';

    public function handle(MemoryExportService $exports): int
    {
        $count = $exports->cleanupExpired();

        $this->info("Memory export jobs expired: {$count}");

        return self::SUCCESS;
    }
}
