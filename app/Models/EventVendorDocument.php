<?php

namespace App\Models;

use App\Enums\VendorDocumentReviewStatus;
use App\Enums\VendorDocumentType;
use App\Enums\VendorDocumentVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventVendorDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'vendor_id',
        'event_vendor_engagement_id',
        'event_vendor_proposal_id',
        'type',
        'title',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'version',
        'supersedes_id',
        'review_status',
        'visibility',
        'effective_date',
        'expires_at',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => VendorDocumentType::class,
            'review_status' => VendorDocumentReviewStatus::class,
            'visibility' => VendorDocumentVisibility::class,
            'effective_date' => 'date',
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'version' => 'integer',
            'size_bytes' => 'integer',
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

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(EventVendorEngagement::class, 'event_vendor_engagement_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(EventVendorProposal::class, 'event_vendor_proposal_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function supersededBy(): HasOne
    {
        return $this->hasOne(self::class, 'supersedes_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
