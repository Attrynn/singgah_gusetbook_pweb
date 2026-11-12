<?php

namespace App\Models;

use App\Enums\VendorLogisticsResponsibility;
use App\Enums\VendorLogisticsStatus;
use App\Enums\VendorLogisticsType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventVendorLogisticsRequirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'event_vendor_engagement_id',
        'event_station_id',
        'type',
        'description',
        'responsibility',
        'due_at',
        'status',
        'resolution_note',
        'fulfilled_by',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VendorLogisticsType::class,
            'responsibility' => VendorLogisticsResponsibility::class,
            'status' => VendorLogisticsStatus::class,
            'due_at' => 'datetime',
            'fulfilled_at' => 'datetime',
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

    public function station(): BelongsTo
    {
        return $this->belongsTo(EventStation::class, 'event_station_id');
    }

    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }
}
