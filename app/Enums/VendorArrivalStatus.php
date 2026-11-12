<?php

namespace App\Enums;

enum VendorArrivalStatus: string
{
    case Expected = 'expected';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case SetupInProgress = 'setup_in_progress';
    case Ready = 'ready';
    case Departed = 'departed';

    public function label(): string
    {
        return match ($this) {
            self::Expected => 'Diharapkan',
            self::Confirmed => 'Dikonfirmasi',
            self::Arrived => 'Tiba',
            self::SetupInProgress => 'Persiapan',
            self::Ready => 'Siap',
            self::Departed => 'Meninggalkan Lokasi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Expected => 'badge-muted',
            self::Confirmed => 'badge-info',
            self::Arrived => 'badge-success',
            self::SetupInProgress => 'badge-warning',
            self::Ready => 'badge-success',
            self::Departed => 'badge-neutral',
        };
    }

    public function isOnsite(): bool
    {
        return in_array($this, [self::Arrived, self::SetupInProgress, self::Ready], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Expected => [self::Confirmed, self::Arrived],
            self::Confirmed => [self::Arrived],
            self::Arrived => [self::SetupInProgress, self::Ready, self::Departed],
            self::SetupInProgress => [self::Ready, self::Departed],
            self::Ready => [self::Departed, self::SetupInProgress],
            self::Departed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
