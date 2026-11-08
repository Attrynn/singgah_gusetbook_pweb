<?php

namespace App\Enums;

enum EventOwnerRole: string
{
    case Groom = 'groom';
    case Bride = 'bride';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Groom => 'Groom',
            self::Bride => 'Bride',
            self::Partner => 'Partner',
        };
    }

    /**
     * Maps a legacy EventUserRole to the equivalent EventOwnerRole.
     * Used when migrating event_user records to event_owner_memberships.
     */
    public static function fromEventUserRole(EventUserRole $role): self
    {
        return match ($role) {
            EventUserRole::Groom => self::Groom,
            EventUserRole::Bride => self::Bride,
            EventUserRole::Partner => self::Partner,
        };
    }
}
