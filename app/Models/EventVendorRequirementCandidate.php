<?php

namespace App\Models;

use App\Enums\VendorCandidateState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorRequirementCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_vendor_requirement_id',
        'vendor_id',
        'state',
        'is_preferred',
        'notes',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'state' => VendorCandidateState::class,
            'is_preferred' => 'boolean',
        ];
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(EventVendorRequirement::class, 'event_vendor_requirement_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
