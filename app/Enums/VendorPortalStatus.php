<?php

namespace App\Enums;

enum VendorPortalStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Diundang',
            self::Active => 'Aktif',
            self::Revoked => 'Dicabut',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Invited => 'badge-warning',
            self::Active => 'badge-success',
            self::Revoked => 'badge-danger',
            self::Expired => 'badge-muted',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Invited => [self::Active, self::Revoked, self::Expired],
            self::Active => [self::Revoked],
            self::Revoked => [self::Invited],
            self::Expired => [self::Invited, self::Revoked],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
