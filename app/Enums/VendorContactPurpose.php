<?php

namespace App\Enums;

enum VendorContactPurpose: string
{
    case Business = 'business';
    case Sales = 'sales';
    case Operational = 'operational';
    case Onsite = 'onsite';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Kontak Bisnis',
            self::Sales => 'Kontak Penjualan',
            self::Operational => 'Kontak Operasional',
            self::Onsite => 'Kontak di Lokasi',
            self::Emergency => 'Kontak Darurat',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Business => 'badge-info',
            self::Sales => 'badge-neutral',
            self::Operational => 'badge-info',
            self::Onsite => 'badge-warning',
            self::Emergency => 'badge-danger',
        };
    }
}
