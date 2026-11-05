<?php

namespace App\Enums;

enum UserRole: string
{
    case Superadmin = 'superadmin';
    case Owner = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Owner => 'Owner',
        };
    }
}
