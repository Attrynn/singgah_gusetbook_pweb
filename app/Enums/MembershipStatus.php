<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoked => 'Revoked',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
