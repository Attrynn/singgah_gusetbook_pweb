<?php

namespace App\Enums;

enum VendorLogisticsType: string
{
    case Electricity = 'electricity';
    case Water = 'water';
    case LoadingAccess = 'loading_access';
    case SetupArea = 'setup_area';
    case Storage = 'storage';
    case EquipmentSupport = 'equipment_support';
    case Technical = 'technical';
    case DeliveryAccess = 'delivery_access';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Electricity => 'Listrik',
            self::Water => 'Air',
            self::LoadingAccess => 'Akses Bongkar Muat',
            self::SetupArea => 'Area Persiapan',
            self::Storage => 'Penyimpanan',
            self::EquipmentSupport => 'Dukungan Peralatan',
            self::Technical => 'Teknis',
            self::DeliveryAccess => 'Akses Pengiriman',
            self::Other => 'Lainnya',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Electricity => 'badge-neutral',
            self::Water => 'badge-neutral',
            self::LoadingAccess => 'badge-neutral',
            self::SetupArea => 'badge-neutral',
            self::Storage => 'badge-neutral',
            self::EquipmentSupport => 'badge-neutral',
            self::Technical => 'badge-info',
            self::DeliveryAccess => 'badge-neutral',
            self::Other => 'badge-muted',
        };
    }
}
