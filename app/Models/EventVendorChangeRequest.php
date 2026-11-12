<?php

namespace App\Models;

use App\Enums\VendorChangeRequestStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'requested_by_user_id',
        'requested_by_contact_id',
        'title',
        'description',
        'proposed_changes',
        'impact',
        'status',
        'decided_by',
        'decided_at',
        'decision_notes',
        'submitted_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorChangeRequestStatus::class,
            'proposed_changes' => 'array',
            'decided_at' => 'datetime',
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
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

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function requestedByContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'requested_by_contact_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
