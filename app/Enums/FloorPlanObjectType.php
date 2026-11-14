<?php

namespace App\Enums;

/**
 * Registry of canvas object types the backend accepts.
 *
 * New object types must be added here before the editor can persist them, so
 * the document validator never has to trust a client-supplied type string.
 */
enum FloorPlanObjectType: string
{
    // Seating
    case Chair = 'chair';
    case RoundTable = 'round_table';
    case RectangleTable = 'rectangle_table';
    case SquareTable = 'square_table';
    case VipTable = 'vip_table';
    case FamilyTable = 'family_table';
    case CustomTable = 'custom_table';

    // Venue
    case Stage = 'stage';
    case DanceFloor = 'dance_floor';
    case Entrance = 'entrance';
    case Exit = 'exit';
    case Door = 'door';
    case Wall = 'wall';
    case Pillar = 'pillar';
    case Partition = 'partition';
    case Staircase = 'staircase';
    case Aisle = 'aisle';

    // Operational
    case BuffetStation = 'buffet_station';
    case CateringStation = 'catering_station';
    case PhotoBooth = 'photo_booth';
    case GuestReception = 'guest_reception';
    case RegistrationDesk = 'registration_desk';
    case VendorStation = 'vendor_station';
    case StaffStation = 'staff_station';
    case SoundArea = 'sound_area';
    case LightingArea = 'lighting_area';
    case RestrictedArea = 'restricted_area';

    // General shapes
    case Rectangle = 'rectangle';
    case Circle = 'circle';
    case Ellipse = 'ellipse';
    case Line = 'line';
    case Polygon = 'polygon';
    case TextLabel = 'text_label';
    case CustomShape = 'custom_shape';

    public function category(): string
    {
        return match ($this) {
            self::Chair,
            self::RoundTable,
            self::RectangleTable,
            self::SquareTable,
            self::VipTable,
            self::FamilyTable,
            self::CustomTable => 'seating',
            self::Stage,
            self::DanceFloor,
            self::Entrance,
            self::Exit,
            self::Door,
            self::Wall,
            self::Pillar,
            self::Partition,
            self::Staircase,
            self::Aisle => 'venue',
            self::BuffetStation,
            self::CateringStation,
            self::PhotoBooth,
            self::GuestReception,
            self::RegistrationDesk,
            self::VendorStation,
            self::StaffStation,
            self::SoundArea,
            self::LightingArea,
            self::RestrictedArea => 'operational',
            self::Rectangle,
            self::Circle,
            self::Ellipse,
            self::Line,
            self::Polygon,
            self::TextLabel,
            self::CustomShape => 'general',
        };
    }

    public function isTable(): bool
    {
        return in_array($this, [
            self::RoundTable,
            self::RectangleTable,
            self::SquareTable,
            self::VipTable,
            self::FamilyTable,
            self::CustomTable,
        ], true);
    }

    public function isChair(): bool
    {
        return $this === self::Chair;
    }

    public function label(): string
    {
        return match ($this) {
            self::Chair => 'Kursi',
            self::RoundTable => 'Meja Bulat',
            self::RectangleTable => 'Meja Persegi Panjang',
            self::SquareTable => 'Meja Persegi',
            self::VipTable => 'Meja VIP',
            self::FamilyTable => 'Meja Keluarga',
            self::CustomTable => 'Meja Kustom',
            self::Stage => 'Panggung',
            self::DanceFloor => 'Lantai Dansa',
            self::Entrance => 'Pintu Masuk',
            self::Exit => 'Pintu Keluar',
            self::Door => 'Pintu',
            self::Wall => 'Dinding',
            self::Pillar => 'Pilar',
            self::Partition => 'Partisi',
            self::Staircase => 'Tangga',
            self::Aisle => 'Lorong',
            self::BuffetStation => 'Stasiun Buffet',
            self::CateringStation => 'Stasiun Katering',
            self::PhotoBooth => 'Photo Booth',
            self::GuestReception => 'Resepsi Tamu',
            self::RegistrationDesk => 'Meja Registrasi',
            self::VendorStation => 'Stasiun Vendor',
            self::StaffStation => 'Stasiun Staf',
            self::SoundArea => 'Area Sound',
            self::LightingArea => 'Area Lighting',
            self::RestrictedArea => 'Area Terbatas',
            self::Rectangle => 'Persegi Panjang',
            self::Circle => 'Lingkaran',
            self::Ellipse => 'Elips',
            self::Line => 'Garis',
            self::Polygon => 'Poligon',
            self::TextLabel => 'Label Teks',
            self::CustomShape => 'Bentuk Kustom',
        };
    }
}
