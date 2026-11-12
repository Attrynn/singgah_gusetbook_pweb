<?php

namespace App\Models;

use App\Enums\VendorIssueSeverity;
use App\Enums\VendorIssueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'event_incident_id',
        'title',
        'description',
        'severity',
        'status',
        'reported_by',
        'assigned_to',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'severity' => VendorIssueSeverity::class,
            'status' => VendorIssueStatus::class,
            'resolved_at' => 'datetime',
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

    public function incident(): BelongsTo
    {
        return $this->belongsTo(EventIncident::class, 'event_incident_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
