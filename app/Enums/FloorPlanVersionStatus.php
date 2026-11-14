<?php

namespace App\Enums;

enum FloorPlanVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Diterbitkan',
            self::Archived => 'Diarsipkan',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
