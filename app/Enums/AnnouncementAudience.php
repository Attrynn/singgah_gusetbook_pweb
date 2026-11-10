<?php

namespace App\Enums;

enum AnnouncementAudience: string
{
    case AllStaff = 'all_staff';
    case Station = 'station';
    case SelectedStaff = 'selected_staff';
    case Profile = 'profile';

    public function label(): string
    {
        return match ($this) {
            self::AllStaff => 'Semua staf aktif',
            self::Station => 'Anggota stasiun tertentu',
            self::SelectedStaff => 'Staf terpilih',
            self::Profile => 'Profil tanggung jawab tertentu',
        };
    }
}
