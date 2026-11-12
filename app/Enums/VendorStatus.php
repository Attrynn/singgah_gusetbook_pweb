<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case PendingVerification = 'pending_verification';
    case Restricted = 'restricted';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::PendingVerification => 'Menunggu Verifikasi',
            self::Restricted => 'Dibatasi',
            self::Archived => 'Diarsipkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Active => 'badge-success',
            self::Inactive => 'badge-muted',
            self::PendingVerification => 'badge-warning',
            self::Restricted => 'badge-danger',
            self::Archived => 'badge-muted',
        };
    }

    public function isUsable(): bool
    {
        return in_array($this, [self::Active, self::PendingVerification, self::Restricted], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingVerification => [self::Active, self::Restricted, self::Archived, self::Inactive],
            self::Active => [self::Inactive, self::Restricted, self::Archived],
            self::Inactive => [self::Active, self::Archived],
            self::Restricted => [self::Active, self::Inactive, self::Archived],
            self::Archived => [self::PendingVerification, self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
