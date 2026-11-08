<?php

namespace App\Enums;

enum SetupStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case ReadyForReview = 'ready_for_review';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::ReadyForReview => 'Ready for Review',
            self::Ready => 'Ready',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::NotStarted => 'badge-neutral',
            self::InProgress => 'badge-info',
            self::ReadyForReview => 'badge-warning',
            self::Ready => 'badge-success',
        };
    }
}
