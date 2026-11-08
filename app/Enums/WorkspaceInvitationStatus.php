<?php

namespace App\Enums;

enum WorkspaceInvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Accepted => 'Accepted',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * An invitation is usable only when it is in the Pending state.
     * Expiry is checked separately against the expires_at timestamp.
     */
    public function isUsable(): bool
    {
        return $this === self::Pending;
    }
}
