<?php

namespace App\Enums;

enum EventUserRole: string
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
}
