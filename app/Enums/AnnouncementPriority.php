<?php

namespace App\Enums;

enum AnnouncementPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::High => 'Penting',
            self::Critical => 'Kritis',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Normal => 'badge-neutral',
            self::High => 'badge-warning',
            self::Critical => 'badge-danger',
        };
    }

    public function requiresAcknowledgementByDefault(): bool
    {
        return $this === self::Critical;
    }
}
