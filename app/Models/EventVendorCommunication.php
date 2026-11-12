<?php

namespace App\Models;

use App\Enums\VendorCommunicationDirection;
use App\Enums\VendorCommunicationType;
use App\Enums\VendorCommunicationVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorCommunication extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'direction',
        'visibility',
        'type',
        'author_user_id',
        'author_contact_id',
        'body',
        'event_vendor_deliverable_id',
        'event_vendor_change_request_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => VendorCommunicationDirection::class,
            'visibility' => VendorCommunicationVisibility::class,
            'type' => VendorCommunicationType::class,
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

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function authorContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'author_contact_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(EventVendorDeliverable::class, 'event_vendor_deliverable_id');
    }

    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(EventVendorChangeRequest::class, 'event_vendor_change_request_id');
    }
}
