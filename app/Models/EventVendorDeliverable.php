<?php

namespace App\Models;

use App\Enums\VendorDeliverableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventVendorDeliverable extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'title',
        'description',
        'vendor_contact_id',
        'reviewer_membership_id',
        'internal_task_id',
        'due_at',
        'status',
        'sequence',
        'accepted_by',
        'accepted_at',
        'acceptance_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorDeliverableStatus::class,
            'due_at' => 'datetime',
            'accepted_at' => 'datetime',
            'sequence' => 'integer',
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

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(EventVendorEngagement::class, 'event_vendor_engagement_id');
    }

    public function vendorContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'vendor_contact_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'reviewer_membership_id');
    }

    public function internalTask(): BelongsTo
    {
        return $this->belongsTo(EventTask::class, 'internal_task_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EventVendorDeliverableSubmission::class);
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && $this->status->isOpen();
    }
}
