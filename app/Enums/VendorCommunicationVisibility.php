<?php

namespace App\Enums;

enum VendorCommunicationVisibility: string
{
    case InternalOnly = 'internal_only';
    case VendorVisible = 'vendor_visible';

    public function label(): string
    {
        return match ($this) {
            self::InternalOnly => 'Internal',
            self::VendorVisible => 'Terlihat Vendor',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InternalOnly => 'badge-muted',
            self::VendorVisible => 'badge-info',
        };
    }
}
