<?php

namespace App\Models;

use App\Enums\VendorProposalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventVendorProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_requirement_id',
        'vendor_id',
        'vendor_service_package_id',
        'title',
        'service_description',
        'quoted_amount',
        'currency',
        'inclusions',
        'exclusions',
        'valid_until',
        'proposed_schedule',
        'status',
        'evaluation_notes',
        'evaluation_rating',
        'submitted_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorProposalStatus::class,
            'quoted_amount' => 'decimal:2',
            'inclusions' => 'array',
            'exclusions' => 'array',
            'valid_until' => 'date',
            'submitted_at' => 'datetime',
            'evaluation_rating' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(EventVendorRequirement::class, 'event_vendor_requirement_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(VendorServicePackage::class, 'vendor_service_package_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventVendorDocument::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(EventVendorEngagement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        if ($this->valid_until === null) {
            return false;
        }

        return $this->valid_until->isPast() && ! in_array($this->status, [
            VendorProposalStatus::Selected,
            VendorProposalStatus::Rejected,
            VendorProposalStatus::Withdrawn,
        ], true);
    }
}
