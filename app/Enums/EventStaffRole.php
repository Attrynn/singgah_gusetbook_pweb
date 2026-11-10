<?php

namespace App\Enums;

enum EventStaffRole: string
{
    case EventManager = 'event_manager';
    case OperationsCoordinator = 'operations_coordinator';
    case StationSupervisor = 'station_supervisor';
    case GuestManager = 'guest_manager';
    case GuestRelations = 'guest_relations';
    case CheckinSupervisor = 'checkin_supervisor';
    case CheckinStaff = 'checkin_staff';
    case SeatingUsher = 'seating_usher';
    case MediaCurator = 'media_curator';
    case GiftSteward = 'gift_steward';
    case GeneralStaff = 'general_staff';
    case Observer = 'observer';

    public function label(): string
    {
        return match ($this) {
            self::EventManager => 'Event Manager',
            self::OperationsCoordinator => 'Koordinator Operasional',
            self::StationSupervisor => 'Supervisor Stasiun',
            self::GuestManager => 'Guest Manager',
            self::GuestRelations => 'Relasi Tamu',
            self::CheckinSupervisor => 'Check-in Supervisor',
            self::CheckinStaff => 'Check-in Staff',
            self::SeatingUsher => 'Usher',
            self::MediaCurator => 'Kurator Media',
            self::GiftSteward => 'Petugas Hadiah',
            self::GeneralStaff => 'Staf Umum',
            self::Observer => 'Pengamat',
        };
    }

    /**
     * Operational profiles that may be delegated by a manager.
     *
     * @return array<int, self>
     */
    public static function operationalProfiles(): array
    {
        return [
            self::EventManager,
            self::OperationsCoordinator,
            self::StationSupervisor,
            self::GuestManager,
            self::GuestRelations,
            self::CheckinSupervisor,
            self::CheckinStaff,
            self::SeatingUsher,
            self::MediaCurator,
            self::GiftSteward,
            self::GeneralStaff,
            self::Observer,
        ];
    }
}
