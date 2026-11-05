<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Ongoing => 'Ongoing',
            self::Done => 'Done',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Planned => 'badge-neutral',
            self::Ongoing => 'badge-info',
            self::Done => 'badge-success',
        };
    }
}
