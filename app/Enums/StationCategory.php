<?php

namespace App\Enums;

enum StationCategory: string
{
    case Registration = 'registration';
    case GuestWelcome = 'guest_welcome';
    case GuestRelations = 'guest_relations';
    case Information = 'information';
    case VipReception = 'vip_reception';
    case VenueCoordination = 'venue_coordination';
    case Technical = 'technical';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registrasi',
            self::GuestWelcome => 'Penyambutan Tamu',
            self::GuestRelations => 'Relasi Tamu',
            self::Information => 'Informasi',
            self::VipReception => 'Resepsi VIP',
            self::VenueCoordination => 'Koordinasi Venue',
            self::Technical => 'Teknis',
            self::General => 'Operasional Umum',
        };
    }
}
