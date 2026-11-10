<?php

namespace App\Enums;

/**
 * Lifecycle of an identified individual inside an invitation party.
 *
 * The party itself keeps the legacy `guests` row and its `max_attendees`
 * quota semantics; this status only describes the individual membership.
 */
enum GuestPartyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
            self::Removed => 'Dihapus',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'badge-success',
            self::Inactive => 'badge-neutral',
            self::Removed => 'badge-danger',
        };
    }
}
