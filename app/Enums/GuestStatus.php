<?php

namespace App\Enums;

enum GuestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Blocked => 'Blocked',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'badge-success',
            self::Inactive => 'badge-neutral',
            self::Blocked => 'badge-danger',
        };
    }
}
