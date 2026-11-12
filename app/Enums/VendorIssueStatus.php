<?php

namespace App\Enums;

enum VendorIssueStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Acknowledged => 'Diakui',
            self::InProgress => 'Ditangani',
            self::Resolved => 'Teratasi',
            self::Closed => 'Ditutup',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'badge-danger',
            self::Acknowledged => 'badge-warning',
            self::InProgress => 'badge-info',
            self::Resolved => 'badge-success',
            self::Closed => 'badge-muted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Acknowledged, self::InProgress], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Acknowledged, self::InProgress, self::Resolved, self::Closed],
            self::Acknowledged => [self::InProgress, self::Resolved, self::Closed],
            self::InProgress => [self::Resolved, self::Closed],
            self::Resolved => [self::Closed, self::InProgress],
            self::Closed => [self::InProgress],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
