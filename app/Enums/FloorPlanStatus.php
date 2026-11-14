<?php

namespace App\Enums;

enum FloorPlanStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Archived => 'Diarsipkan',
        };
    }
}
