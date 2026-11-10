<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventStationAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'station_id',
        'staff_membership_id',
        'is_supervisor',
        'assigned_by',
        'assigned_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_supervisor' => 'boolean',
            'assigned_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(EventStation::class, 'station_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'staff_membership_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
