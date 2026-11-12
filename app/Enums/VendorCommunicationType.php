<?php

namespace App\Enums;

enum VendorCommunicationType: string
{
    case Note = 'note';
    case Clarification = 'clarification';
    case ServiceConfirmation = 'service_confirmation';
    case ScheduleConfirmation = 'schedule_confirmation';
    case DeliverableUpdate = 'deliverable_update';
    case LogisticsUpdate = 'logistics_update';
    case ChangeDiscussion = 'change_discussion';
    case Resolution = 'resolution';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Catatan',
            self::Clarification => 'Klarifikasi',
            self::ServiceConfirmation => 'Konfirmasi Layanan',
            self::ScheduleConfirmation => 'Konfirmasi Jadwal',
            self::DeliverableUpdate => 'Pembaruan Deliverable',
            self::LogisticsUpdate => 'Pembaruan Logistik',
            self::ChangeDiscussion => 'Diskusi Perubahan',
            self::Resolution => 'Penyelesaian',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Note => 'badge-muted',
            self::Clarification => 'badge-info',
            self::ServiceConfirmation => 'badge-neutral',
            self::ScheduleConfirmation => 'badge-neutral',
            self::DeliverableUpdate => 'badge-info',
            self::LogisticsUpdate => 'badge-info',
            self::ChangeDiscussion => 'badge-warning',
            self::Resolution => 'badge-success',
        };
    }
}
