<?php

namespace App\Enums;

/**
 * Operational lifecycle of an event, deliberately separate from the business
 * EventStatus (draft/published/live/completed/archived).
 */
enum OperationsStatus: string
{
    case Draft = 'draft';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Preparing => 'Persiapan',
            self::Ready => 'Siap',
            self::Active => 'Berjalan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-neutral',
            self::Preparing => 'badge-info',
            self::Ready => 'badge-success',
            self::Active => 'badge-warning',
            self::Completed => 'badge-muted',
            self::Cancelled => 'badge-danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Preparing, self::Cancelled],
            self::Preparing => [self::Draft, self::Ready, self::Cancelled],
            self::Ready => [self::Preparing, self::Active, self::Cancelled],
            self::Active => [self::Completed, self::Cancelled],
            self::Completed => [self::Active],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
