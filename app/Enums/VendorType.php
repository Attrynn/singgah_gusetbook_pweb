<?php

namespace App\Enums;

enum VendorType: string
{
    case Company = 'company';
    case Individual = 'individual';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Perusahaan',
            self::Individual => 'Perorangan',
            self::Other => 'Lainnya',
        };
    }

    public function badge(): string
    {
        return 'badge-neutral';
    }
}
