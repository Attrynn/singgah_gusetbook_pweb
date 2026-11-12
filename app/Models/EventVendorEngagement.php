<?php

namespace App\Models;

use App\Enums\VendorEngagementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventVendorEngagement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'vendor_id',
        'event_vendor_requirement_id',
        'event_vendor_proposal_id',
        'vendor_service_id',
        'vendor_service_package_id',
        'coordinator_membership_id',
        'vendor_contact_id',
        'title',
        'status',
        'service_snapshot',
        'package_snapshot',
        'scope_snapshot',
        'agreed_amount',
        'currency',
        'selection_reason',
        'selected_by',
        'selected_at',
        'confirmed_by',
        'confirmed_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'completed_at',
        'closed_by',
        'closed_at',
        'closure_notes',
        'closure_snapshot',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorEngagementStatus::class,
            'service_snapshot' => 'array',
            'package_snapshot' => 'array',
            'scope_snapshot' => 'array',
            'closure_snapshot' => 'array',
            'agreed_amount' => 'decimal:2',
            'selected_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(EventVendorRequirement::class, 'event_vendor_requirement_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(EventVendorProposal::class, 'event_vendor_proposal_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(VendorService::class, 'vendor_service_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(VendorServicePackage::class, 'vendor_service_package_id');
    }

    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'coordinator_membership_id');
    }

    public function vendorContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'vendor_contact_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventVendorDocument::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(EventVendorDeliverable::class);
    }

    public function scheduleReferences(): HasMany
    {
        return $this->hasMany(EventVendorScheduleReference::class);
    }

    public function logisticsRequirements(): HasMany
    {
        return $this->hasMany(EventVendorLogisticsRequirement::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(EventVendorChangeRequest::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(EventVendorEngagementRevision::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(EventVendorCommunication::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(EventVendorIssue::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(EventVendorPerformanceReview::class);
    }

    public function portalAccess(): HasMany
    {
        return $this->hasMany(VendorPortalEngagementAccess::class);
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null || $this->status->isTerminal();
    }
}
