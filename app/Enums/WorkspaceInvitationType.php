<?php

namespace App\Enums;

enum WorkspaceInvitationType: string
{
    case Staff = 'staff';
    case Couple = 'couple';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Couple => 'Couple',
        };
    }
}
