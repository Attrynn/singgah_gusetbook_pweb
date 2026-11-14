<?php

namespace App\Enums;

/**
 * Lifecycle of a memory export job.
 */
enum MemoryExportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antrian',
            self::Running => 'Diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Queued => 'badge-neutral',
            self::Running => 'badge-info',
            self::Completed => 'badge-success',
            self::Failed => 'badge-danger',
            self::Expired => 'badge-warning',
        };
    }

    /**
     * Terminal states no longer accept transitions.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::Expired], true);
    }
}
