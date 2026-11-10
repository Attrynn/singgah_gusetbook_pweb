<?php

namespace App\Enums;

enum IncidentCategory: string
{
    case Technical = 'technical';
    case Staffing = 'staffing';
    case Venue = 'venue';
    case Equipment = 'equipment';
    case Schedule = 'schedule';
    case GuestAssistance = 'guest_assistance';
    case Operations = 'operations';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Teknis',
            self::Staffing => 'Kepegawaian',
            self::Venue => 'Venue',
            self::Equipment => 'Peralatan',
            self::Schedule => 'Jadwal',
            self::GuestAssistance => 'Bantuan Tamu',
            self::Operations => 'Operasional',
            self::Other => 'Lainnya',
        };
    }
}
