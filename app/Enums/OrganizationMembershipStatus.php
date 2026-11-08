<?php

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Revoked => 'Revoked',
        };
    }

    /** Only Active members may access workspace resources. */
    public function isAccessible(): bool
    {
        return $this === self::Active;
    }
}
