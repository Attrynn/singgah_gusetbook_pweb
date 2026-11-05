<?php

namespace App\Enums;

enum SongStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Played = 'played';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Played => 'Played',
            self::Rejected => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-warning',
            self::Approved => 'badge-info',
            self::Played => 'badge-success',
            self::Rejected => 'badge-danger',
        };
    }
}
