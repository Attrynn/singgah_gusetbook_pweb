<?php

namespace App\Enums;

enum VendorDocumentReviewStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::UnderReview => 'Ditinjau',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Archived => 'Diarsipkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-muted',
            self::UnderReview => 'badge-warning',
            self::Approved => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Expired => 'badge-muted',
            self::Archived => 'badge-muted',
        };
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::UnderReview, self::Archived],
            self::UnderReview => [self::Approved, self::Rejected, self::Archived],
            self::Approved => [self::Expired, self::Archived, self::UnderReview],
            self::Rejected => [self::UnderReview, self::Archived],
            self::Expired => [self::UnderReview, self::Archived],
            self::Archived => [self::UnderReview],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
