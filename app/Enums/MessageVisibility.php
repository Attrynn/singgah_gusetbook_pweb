<?php

namespace App\Enums;

enum MessageVisibility: string
{
    case Public = 'public';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Private => 'Private',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Public => 'badge-info',
            self::Private => 'badge-neutral',
        };
    }
}
