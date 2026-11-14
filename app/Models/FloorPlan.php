<?php

namespace App\Models;

use App\Enums\FloorPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FloorPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'venue_id',
        'venue_space_id',
        'name',
        'description',
        'status',
        'archived_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => FloorPlanStatus::class,
            'archived_at' => 'datetime',
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

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function venueSpace(): BelongsTo
    {
        return $this->belongsTo(VenueSpace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FloorPlanVersion::class)->orderByDesc('version_number');
    }

    public function currentDraft(): HasOne
    {
        return $this->hasOne(FloorPlanVersion::class)
            ->where('status', 'draft')
            ->latestOfMany('version_number');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(FloorPlanVersion::class)
            ->where('status', 'published')
            ->latestOfMany('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(FloorPlanVersion::class)->latestOfMany('version_number');
    }

    public function seatingAssignments(): HasMany
    {
        return $this->hasMany(SeatingAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FloorPlanStatus::Active->value);
    }
}
