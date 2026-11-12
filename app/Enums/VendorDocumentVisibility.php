<?php

namespace App\Enums;

enum VendorDocumentVisibility: string
{
    case InternalOnly = 'internal_only';
    case VendorVisible = 'vendor_visible';
    case Shared = 'shared';
    case RestrictedCommercial = 'restricted_commercial';

    public function label(): string
    {
        return match ($this) {
            self::InternalOnly => 'Internal',
            self::VendorVisible => 'Terlihat Vendor',
            self::Shared => 'Dibagikan',
            self::RestrictedCommercial => 'Komersial Terbatas',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InternalOnly => 'badge-muted',
            self::VendorVisible => 'badge-info',
            self::Shared => 'badge-info',
            self::RestrictedCommercial => 'badge-danger',
        };
    }
}
