<?php

namespace App\Enums;

enum VendorChangeRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Ditinjau',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Withdrawn => 'Ditarik',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-muted',
            self::Submitted => 'badge-info',
            self::UnderReview => 'badge-warning',
            self::Approved => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Withdrawn => 'badge-muted',
        };
    }

    public function isPending(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Withdrawn], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderReview, self::Approved, self::Rejected, self::Withdrawn],
            self::UnderReview => [self::Approved, self::Rejected, self::Withdrawn],
            self::Approved => [],
            self::Rejected => [],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
