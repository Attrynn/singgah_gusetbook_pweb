<?php

namespace App\Models;

use App\Enums\SeatingAssignmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'floor_plan_id',
        'floor_plan_version_id',
        'table_object_id',
        'seat_object_id',
        'guest_id',
        'guest_party_membership_id',
        'headcount',
        'status',
        'assigned_by',
        'assigned_at',
        'released_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeatingAssignmentStatus::class,
            'headcount' => 'integer',
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FloorPlanVersion::class, 'floor_plan_version_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GuestPartyMembership::class, 'guest_party_membership_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SeatingAssignmentStatus::Active->value);
    }
}
