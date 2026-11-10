<?php

namespace App\Enums;

enum ReadinessStatus: string
{
    case Ready = 'ready';
    case NotReady = 'not_ready';
    case Warning = 'warning';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Siap',
            self::NotReady => 'Belum Siap',
            self::Warning => 'Peringatan',
            self::NotApplicable => 'Tidak Berlaku',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Ready => 'badge-success',
            self::NotReady => 'badge-danger',
            self::Warning => 'badge-warning',
            self::NotApplicable => 'badge-muted',
        };
    }

    public function blocksReadiness(): bool
    {
        return $this === self::NotReady;
    }
}
