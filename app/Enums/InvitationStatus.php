<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case NotSent = 'not_sent';
    case LinkOpened = 'link_opened';
    case SharedManually = 'shared_manually';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::NotSent => 'Not sent',
            self::LinkOpened => 'Link opened',
            self::SharedManually => 'Shared manually',
            self::Sent => 'Sent',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::NotSent => 'badge-neutral',
            self::LinkOpened => 'badge-info',
            self::SharedManually => 'badge-warning',
            self::Sent => 'badge-success',
        };
    }
}
