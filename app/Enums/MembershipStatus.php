<?php

namespace App\Enums;

enum MembershipStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Declined = 'declined';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Removed = 'removed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Konfirmasi',
            self::Active => 'Aktif',
            self::Declined => 'Ditolak',
            self::Suspended => 'Ditangguhkan',
            self::Revoked => 'Dicabut',
            self::Removed => 'Dikeluarkan',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'badge-warning',
            self::Active => 'badge-success',
            self::Declined => 'badge-muted',
            self::Suspended => 'badge-danger',
            self::Revoked => 'badge-muted',
            self::Removed => 'badge-muted',
            self::Expired => 'badge-muted',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * States that still hold a seat in the team but grant no authorization.
     *
     * @return array<int, self>
     */
    public static function nonAuthorizing(): array
    {
        return [self::Pending, self::Declined, self::Suspended, self::Revoked, self::Removed, self::Expired];
    }
}
