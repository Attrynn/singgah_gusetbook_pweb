<?php

namespace App\Enums;

enum VendorLogisticsResponsibility: string
{
    case Vendor = 'vendor';
    case Organizer = 'organizer';
    case Venue = 'venue';
    case Shared = 'shared';

    public function label(): string
    {
        return match ($this) {
            self::Vendor => 'Vendor',
            self::Organizer => 'Penyelenggara',
            self::Venue => 'Venue',
            self::Shared => 'Bersama',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Vendor => 'badge-info',
            self::Organizer => 'badge-neutral',
            self::Venue => 'badge-neutral',
            self::Shared => 'badge-warning',
        };
    }
}
