<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorPerformanceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'vendor_id',
        'reviewer_user_id',
        'overall_rating',
        'criteria',
        'comments',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'criteria' => 'array',
            'reviewed_at' => 'datetime',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
