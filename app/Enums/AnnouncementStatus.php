<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Terjadwal',
            self::Published => 'Diterbitkan',
            self::Expired => 'Kedaluwarsa',
            self::Withdrawn => 'Ditarik',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-neutral',
            self::Scheduled => 'badge-info',
            self::Published => 'badge-success',
            self::Expired => 'badge-muted',
            self::Withdrawn => 'badge-danger',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::Published;
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Published, self::Withdrawn],
            self::Scheduled => [self::Published, self::Withdrawn, self::Draft],
            self::Published => [self::Expired, self::Withdrawn],
            self::Expired => [],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
