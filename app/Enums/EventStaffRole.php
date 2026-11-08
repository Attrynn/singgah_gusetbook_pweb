<?php

namespace App\Enums;

enum EventStaffRole: string
{
    case EventManager = 'event_manager';
    case GuestManager = 'guest_manager';
    case CheckinSupervisor = 'checkin_supervisor';
    case CheckinStaff = 'checkin_staff';
    case SeatingUsher = 'seating_usher';
    case MediaCurator = 'media_curator';
    case GiftSteward = 'gift_steward';

    public function label(): string
    {
        return match ($this) {
            self::EventManager => 'Event Manager',
            self::GuestManager => 'Guest Manager',
            self::CheckinSupervisor => 'Check-in Supervisor',
            self::CheckinStaff => 'Check-in Staff',
            self::SeatingUsher => 'Seating Usher',
            self::MediaCurator => 'Media Curator',
            self::GiftSteward => 'Gift Steward',
        };
    }
}
