<?php

namespace App\Enums;

enum VendorCommunicationDirection: string
{
    case Internal = 'internal';
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::Inbound => 'Masuk',
            self::Outbound => 'Keluar',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Internal => 'badge-muted',
            self::Inbound => 'badge-info',
            self::Outbound => 'badge-neutral',
        };
    }
}
