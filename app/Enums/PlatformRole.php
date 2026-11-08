<?php

namespace App\Enums;

enum PlatformRole: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Admin => 'Admin',
        };
    }
}
