<?php

namespace App\Enums;

enum VendorCandidateState: string
{
    case Candidate = 'candidate';
    case Shortlisted = 'shortlisted';
    case Preferred = 'preferred';
    case Rejected = 'rejected';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::Candidate => 'Kandidat',
            self::Shortlisted => 'Didaftar Pendek',
            self::Preferred => 'Diutamakan',
            self::Rejected => 'Ditolak',
            self::Selected => 'Dipilih',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Candidate => 'badge-neutral',
            self::Shortlisted => 'badge-info',
            self::Preferred => 'badge-warning',
            self::Rejected => 'badge-danger',
            self::Selected => 'badge-success',
        };
    }
}
