<?php

namespace App\Enums;

enum SpatialReferenceStatus: string
{
    case Active = 'active';
    case NeedsReview = 'needs_review';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::NeedsReview => 'Perlu Ditinjau',
            self::Archived => 'Diarsipkan',
        };
    }
}
