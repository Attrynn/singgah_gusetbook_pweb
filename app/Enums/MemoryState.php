<?php

namespace App\Enums;

/**
 * Moderation state of a single memory contribution.
 */
enum MemoryState: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Hidden => 'Disembunyikan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-warning',
            self::Approved => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Hidden => 'badge-neutral',
        };
    }
}
