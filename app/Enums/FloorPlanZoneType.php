<?php

namespace App\Enums;

enum FloorPlanZoneType: string
{
    case MainReception = 'main_reception';
    case Vip = 'vip';
    case Family = 'family';
    case Guest = 'guest';
    case Stage = 'stage';
    case Dining = 'dining';
    case Buffet = 'buffet';
    case Photography = 'photography';
    case Vendor = 'vendor';
    case Restricted = 'restricted';
    case Entrance = 'entrance';
    case EmergencyExit = 'emergency_exit';
    case Ceremony = 'ceremony';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::MainReception => 'Resepsi Utama',
            self::Vip => 'Area VIP',
            self::Family => 'Area Keluarga',
            self::Guest => 'Area Tamu',
            self::Stage => 'Area Panggung',
            self::Dining => 'Area Makan',
            self::Buffet => 'Area Buffet',
            self::Photography => 'Area Fotografi',
            self::Vendor => 'Area Vendor',
            self::Restricted => 'Area Terbatas',
            self::Entrance => 'Area Masuk',
            self::EmergencyExit => 'Jalur Evakuasi',
            self::Ceremony => 'Area Upacara',
            self::Other => 'Lainnya',
        };
    }
}
