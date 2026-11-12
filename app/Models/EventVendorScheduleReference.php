<?php

namespace App\Models;

use App\Enums\VendorArrivalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorScheduleReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'event_run_of_show_item_id',
        'event_station_id',
        'vendor_contact_id',
        'arrival_status',
        'expected_arrival_at',
        'arrival_at',
        'setup_start_at',
        'setup_end_at',
        'delivery_start_at',
        'delivery_end_at',
        'service_start_at',
        'service_end_at',
        'teardown_start_at',
        'teardown_end_at',
        'ready_at',
        'departed_at',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'arrival_status' => VendorArrivalStatus::class,
            'expected_arrival_at' => 'datetime',
            'arrival_at' => 'datetime',
            'setup_start_at' => 'datetime',
            'setup_end_at' => 'datetime',
            'delivery_start_at' => 'datetime',
            'delivery_end_at' => 'datetime',
            'service_start_at' => 'datetime',
            'service_end_at' => 'datetime',
            'teardown_start_at' => 'datetime',
            'teardown_end_at' => 'datetime',
            'ready_at' => 'datetime',
            'departed_at' => 'datetime',
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

    public function runOfShowItem(): BelongsTo
    {
        return $this->belongsTo(EventRunOfShowItem::class, 'event_run_of_show_item_id');
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(EventStation::class, 'event_station_id');
    }

    public function vendorContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'vendor_contact_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
