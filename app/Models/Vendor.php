<?php

namespace App\Models;

use App\Enums\VendorStatus;
use App\Enums\VendorType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'vendor_category_id',
        'name',
        'legal_name',
        'type',
        'description',
        'email',
        'phone',
        'website',
        'address',
        'internal_notes',
        'status',
        'created_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VendorType::class,
            'status' => VendorStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class, 'vendor_category_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(VendorService::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(EventVendorRequirement::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(EventVendorProposal::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(EventVendorEngagement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventVendorDocument::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(EventVendorPerformanceReview::class);
    }

    public function portalMemberships(): HasMany
    {
        return $this->hasMany(VendorPortalMembership::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === VendorStatus::Active;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null || $this->status === VendorStatus::Archived;
    }

    public static function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VendorStatus::Active->value);
    }

    public static function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
