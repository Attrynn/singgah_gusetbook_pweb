<?php

namespace App\Enums;

enum VendorEngagementStatus: string
{
    case Draft = 'draft';
    case Sourcing = 'sourcing';
    case ProposalReceived = 'proposal_received';
    case Negotiation = 'negotiation';
    case PendingApproval = 'pending_approval';
    case Confirmed = 'confirmed';
    case InPreparation = 'in_preparation';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Sourcing => 'Pencarian',
            self::ProposalReceived => 'Proposal Diterima',
            self::Negotiation => 'Negosiasi',
            self::PendingApproval => 'Menunggu Persetujuan',
            self::Confirmed => 'Terkonfirmasi',
            self::InPreparation => 'Persiapan',
            self::InProgress => 'Berlangsung',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge-muted',
            self::Sourcing => 'badge-info',
            self::ProposalReceived => 'badge-info',
            self::Negotiation => 'badge-warning',
            self::PendingApproval => 'badge-warning',
            self::Confirmed => 'badge-success',
            self::InPreparation => 'badge-info',
            self::InProgress => 'badge-info',
            self::Completed => 'badge-success',
            self::Cancelled => 'badge-danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Confirmed, self::InPreparation, self::InProgress], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sourcing, self::ProposalReceived, self::Negotiation, self::PendingApproval], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sourcing, self::ProposalReceived, self::Negotiation, self::PendingApproval, self::Cancelled],
            self::Sourcing => [self::ProposalReceived, self::Negotiation, self::Cancelled],
            self::ProposalReceived => [self::Negotiation, self::PendingApproval, self::Cancelled],
            self::Negotiation => [self::PendingApproval, self::ProposalReceived, self::Cancelled],
            self::PendingApproval => [self::Confirmed, self::Negotiation, self::Cancelled],
            self::Confirmed => [self::InPreparation, self::InProgress, self::Completed, self::Cancelled],
            self::InPreparation => [self::InProgress, self::Completed, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
