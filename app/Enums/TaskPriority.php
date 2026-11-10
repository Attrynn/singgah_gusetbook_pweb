<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Rendah',
            self::Medium => 'Sedang',
            self::High => 'Tinggi',
            self::Critical => 'Kritis',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Low => 'badge-muted',
            self::Medium => 'badge-neutral',
            self::High => 'badge-warning',
            self::Critical => 'badge-danger',
        };
    }
}
