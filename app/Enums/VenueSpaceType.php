<?php

namespace App\Enums;

enum VenueSpaceType: string
{
    case Ballroom = 'ballroom';
    case VipRoom = 'vip_room';
    case Outdoor = 'outdoor';
    case Garden = 'garden';
    case Akad = 'akad';
    case Reception = 'reception';
    case Ceremony = 'ceremony';
    case Dining = 'dining';
    case Stage = 'stage';
    case Buffet = 'buffet';
    case LoadingArea = 'loading_area';
    case Tent = 'tent';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ballroom => 'Ballroom',
            self::VipRoom => 'Ruang VIP',
            self::Outdoor => 'Area Terbuka',
            self::Garden => 'Taman',
            self::Akad => 'Ruang Akad',
            self::Reception => 'Ruang Resepsi',
            self::Ceremony => 'Area Upacara',
            self::Dining => 'Area Makan',
            self::Stage => 'Area Panggung',
            self::Buffet => 'Area Buffet',
            self::LoadingArea => 'Area Bongkar Muat',
            self::Tent => 'Tenda',
            self::Other => 'Lainnya',
        };
    }
}
