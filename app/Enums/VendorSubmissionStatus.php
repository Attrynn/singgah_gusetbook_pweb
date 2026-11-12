<?php

namespace App\Enums;

enum VendorSubmissionStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ClarificationRequested = 'clarification_requested';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diserahkan',
            self::UnderReview => 'Ditinjau',
            self::Accepted => 'Diterima',
            self::Rejected => 'Ditolak',
            self::ClarificationRequested => 'Minta Klarifikasi',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Submitted => 'badge-info',
            self::UnderReview => 'badge-warning',
            self::Accepted => 'badge-success',
            self::Rejected => 'badge-danger',
            self::ClarificationRequested => 'badge-warning',
        };
    }
}
