<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Assigned => 'Ditugaskan',
            self::InProgress => 'Dikerjakan',
            self::Blocked => 'Terhambat',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'badge-neutral',
            self::Assigned => 'badge-info',
            self::InProgress => 'badge-info',
            self::Blocked => 'badge-warning',
            self::Completed => 'badge-success',
            self::Cancelled => 'badge-muted',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Assigned, self::InProgress, self::Blocked, self::Cancelled],
            self::Assigned => [self::Open, self::InProgress, self::Blocked, self::Cancelled],
            self::InProgress => [self::Blocked, self::Completed, self::Cancelled],
            self::Blocked => [self::InProgress, self::Cancelled],
            self::Completed => [self::Open],
            self::Cancelled => [self::Open],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
