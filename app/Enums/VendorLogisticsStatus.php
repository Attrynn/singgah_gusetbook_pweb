<?php

namespace App\Enums;

enum VendorLogisticsStatus: string
{
    case Requested = 'requested';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Fulfilled = 'fulfilled';
    case Unable = 'unable';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Diminta',
            self::Acknowledged => 'Diakui',
            self::InProgress => 'Diproses',
            self::Fulfilled => 'Terpenuhi',
            self::Unable => 'Tidak Dapat Dipenuhi',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Requested => 'badge-info',
            self::Acknowledged => 'badge-warning',
            self::InProgress => 'badge-warning',
            self::Fulfilled => 'badge-success',
            self::Unable => 'badge-danger',
            self::Cancelled => 'badge-muted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Acknowledged, self::InProgress], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::Acknowledged, self::InProgress, self::Fulfilled, self::Unable, self::Cancelled],
            self::Acknowledged => [self::InProgress, self::Fulfilled, self::Unable, self::Cancelled],
            self::InProgress => [self::Fulfilled, self::Unable, self::Cancelled],
            self::Fulfilled => [self::InProgress],
            self::Unable => [self::InProgress, self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
