<?php

namespace App\Enums;

enum SeatingAssignmentStatus: string
{
    case Active = 'active';
    case Released = 'released';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Released => 'Dilepas',
            self::NeedsReview => 'Perlu Ditinjau',
        };
    }
}
