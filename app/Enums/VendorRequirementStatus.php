<?php

namespace App\Enums;

enum VendorRequirementStatus: string
{
    case Open = 'open';
    case Sourcing = 'sourcing';
    case ProposalsReceived = 'proposals_received';
    case VendorSelected = 'vendor_selected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka',
            self::Sourcing => 'Pencarian Vendor',
            self::ProposalsReceived => 'Proposal Diterima',
            self::VendorSelected => 'Vendor Dipilih',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'badge-info',
            self::Sourcing => 'badge-warning',
            self::ProposalsReceived => 'badge-neutral',
            self::VendorSelected => 'badge-success',
            self::Cancelled => 'badge-muted',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Sourcing, self::ProposalsReceived], true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Sourcing, self::Cancelled],
            self::Sourcing => [self::ProposalsReceived, self::Cancelled],
            self::ProposalsReceived => [self::VendorSelected, self::Sourcing, self::Cancelled],
            self::VendorSelected => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
