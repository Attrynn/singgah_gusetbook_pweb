<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Maybe = 'maybe';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Declined => 'Declined',
            self::Maybe => 'Maybe',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-neutral',
            self::Confirmed => 'badge-success',
            self::Declined => 'badge-danger',
            self::Maybe => 'badge-warning',
        };
    }
}
