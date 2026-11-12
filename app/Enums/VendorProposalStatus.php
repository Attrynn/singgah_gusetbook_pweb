<?php

namespace App\Enums;

enum VendorProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderEvaluation = 'under_evaluation';
    case Selected = 'selected';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Diajukan',
            self::UnderEvaluation => 'Sedang Dievaluasi',
            self::Selected => 'Dipilih',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Withdrawn => 'Ditarik',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-muted',
            self::Submitted => 'badge-info',
            self::UnderEvaluation => 'badge-warning',
            self::Selected => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Expired => 'badge-muted',
            self::Withdrawn => 'badge-muted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Submitted, self::UnderEvaluation], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderEvaluation, self::Selected, self::Rejected, self::Expired, self::Withdrawn],
            self::UnderEvaluation => [self::Selected, self::Rejected, self::Expired, self::Withdrawn],
            self::Selected => [],
            self::Rejected => [],
            self::Expired => [self::UnderEvaluation],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
