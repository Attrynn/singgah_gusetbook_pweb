<?php

namespace App\Enums;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Revoked => 'Revoked',
            self::Inactive => 'Inactive',
        };
    }

    /** Only Active members may access workspace resources. */
    public function isAccessible(): bool
    {
        return $this === self::Active;
    }
}
