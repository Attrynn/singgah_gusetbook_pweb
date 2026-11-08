<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Live = 'live';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Live => 'Live',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-neutral',
            self::Published => 'badge-info',
            self::Live => 'badge-success',
            self::Completed => 'badge-warning',
            self::Archived => 'badge-muted',
        };
    }

    /**
     * Human-facing lifecycle stage label. Maps the internal status to the
     * wording used across the workspace UI (Published = Upcoming, Live = In Progress).
     */
    public function stageLabel(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Upcoming',
            self::Live => 'In Progress',
            self::Completed => 'Completed',
            self::Archived => 'Archived',
        };
    }

    public function isPublic(): bool
    {
        return in_array($this, [self::Published, self::Live, self::Completed], true);
    }

    public function allowsGuestActions(): bool
    {
        return in_array($this, [self::Published, self::Live], true);
    }
}
