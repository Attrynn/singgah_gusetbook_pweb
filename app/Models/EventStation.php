<?php

namespace App\Models;

use App\Enums\StationCategory;
use App\Enums\StationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'slug',
        'category',
        'description',
        'location',
        'instructions',
        'status',
        'supervisor_membership_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => StationCategory::class,
            'status' => StationStatus::class,
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'supervisor_membership_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EventStationAssignment::class, 'station_id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('removed_at');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(
            EventStaffMembership::class,
            'event_station_assignments',
            'station_id',
            'staff_membership_id',
        )->withPivot(['is_supervisor', 'assigned_at', 'removed_at'])->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class, 'station_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(EventIncident::class, 'station_id');
    }

    public function runOfShowItems(): HasMany
    {
        return $this->hasMany(EventRunOfShowItem::class, 'station_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
