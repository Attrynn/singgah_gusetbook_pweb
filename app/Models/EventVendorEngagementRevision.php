<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorEngagementRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_vendor_engagement_id',
        'event_vendor_change_request_id',
        'revision_number',
        'snapshot',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'revision_number' => 'integer',
        ];
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(EventVendorEngagement::class, 'event_vendor_engagement_id');
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(EventVendorChangeRequest::class, 'event_vendor_change_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
