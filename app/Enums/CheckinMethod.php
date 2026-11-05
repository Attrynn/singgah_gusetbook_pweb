<?php

namespace App\Enums;

enum CheckinMethod: string
{
    case Qr = 'qr';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'QR scan',
            self::Manual => 'Manual',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Qr => 'badge-info',
            self::Manual => 'badge-neutral',
        };
    }
}
