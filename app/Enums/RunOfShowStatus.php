<?php

namespace App\Enums;

enum RunOfShowStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Delayed = 'delayed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Delayed => 'Delayed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-neutral',
            self::InProgress => 'badge-info',
            self::Completed => 'badge-success',
            self::Delayed => 'badge-warning',
            self::Cancelled => 'badge-muted',
        };
    }
}
