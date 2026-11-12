<?php

namespace App\Enums;

enum VendorDeliverableStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum Dimulai',
            self::InProgress => 'Dikerjakan',
            self::Submitted => 'Diserahkan',
            self::UnderReview => 'Ditinjau',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
            self::Overdue => 'Terlambat',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::NotStarted => 'badge-muted',
            self::InProgress => 'badge-info',
            self::Submitted => 'badge-warning',
            self::UnderReview => 'badge-warning',
            self::Accepted => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Overdue => 'badge-danger',
            self::Cancelled => 'badge-muted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::NotStarted, self::InProgress, self::Submitted, self::UnderReview, self::Rejected, self::Overdue], true);
    }

    public function isComplete(): bool
    {
        return in_array($this, [self::Accepted, self::Cancelled], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::NotStarted => [self::InProgress, self::Submitted, self::Cancelled],
            self::InProgress => [self::Submitted, self::Cancelled],
            self::Submitted => [self::UnderReview, self::Rejected, self::Accepted, self::Cancelled],
            self::UnderReview => [self::Accepted, self::Rejected, self::Cancelled],
            self::Rejected => [self::InProgress, self::Submitted, self::Cancelled],
            self::Overdue => [self::InProgress, self::Submitted, self::Cancelled],
            self::Accepted => [self::UnderReview],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
