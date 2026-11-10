<?php

namespace App\Enums;

enum RunOfShowStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Delayed = 'delayed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Delayed => 'Delayed',
            self::Skipped => 'Skipped',
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
            self::Skipped => 'badge-muted',
            self::Cancelled => 'badge-muted',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Skipped, self::Cancelled], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::Delayed, self::Skipped, self::Cancelled],
            self::InProgress => [self::Completed, self::Delayed, self::Cancelled],
            self::Delayed => [self::InProgress, self::Completed, self::Skipped, self::Cancelled],
            self::Completed => [self::InProgress],
            self::Skipped => [self::Pending],
            self::Cancelled => [self::Pending],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
