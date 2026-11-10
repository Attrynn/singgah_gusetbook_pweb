<?php

namespace App\Enums;

enum StationStatus: string
{
    case Draft = 'draft';
    case NotReady = 'not_ready';
    case Ready = 'ready';
    case Active = 'active';
    case TemporarilyClosed = 'temporarily_closed';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::NotReady => 'Belum Siap',
            self::Ready => 'Siap',
            self::Active => 'Aktif',
            self::TemporarilyClosed => 'Ditutup Sementara',
            self::Completed => 'Selesai',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-neutral',
            self::NotReady => 'badge-warning',
            self::Ready => 'badge-success',
            self::Active => 'badge-info',
            self::TemporarilyClosed => 'badge-danger',
            self::Completed => 'badge-muted',
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::NotReady, self::Ready],
            self::NotReady => [self::Draft, self::Ready],
            self::Ready => [self::NotReady, self::Active, self::TemporarilyClosed],
            self::Active => [self::TemporarilyClosed, self::Completed],
            self::TemporarilyClosed => [self::Active, self::Completed],
            self::Completed => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isOperational(): bool
    {
        return in_array($this, [self::Ready, self::Active], true);
    }
}
