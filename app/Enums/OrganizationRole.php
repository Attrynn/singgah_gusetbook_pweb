<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Returns true if this role is at least as privileged as $minimum.
     * Hierarchy: Owner > Admin > Member.
     */
    public function isAtLeast(OrganizationRole $minimum): bool
    {
        $hierarchy = [
            self::Owner->value => 3,
            self::Admin->value => 2,
            self::Member->value => 1,
        ];

        return $hierarchy[$this->value] >= $hierarchy[$minimum->value];
    }
}
