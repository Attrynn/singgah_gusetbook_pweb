<?php

namespace App\Models;

use App\Enums\VendorRequirementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventVendorRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'vendor_category_id',
        'title',
        'description',
        'scope',
        'quantity',
        'needed_by',
        'desired_availability',
        'owner_membership_id',
        'notes',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorRequirementStatus::class,
            'needed_by' => 'date',
            'quantity' => 'integer',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(VendorCategory::class, 'vendor_category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'owner_membership_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(EventVendorRequirementCandidate::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(EventVendorProposal::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(EventVendorEngagement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
