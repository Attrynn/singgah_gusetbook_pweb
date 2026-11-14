<?php

namespace App\Models;

use App\Enums\VenueSpaceType;
use App\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueSpace extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'venue_id',
        'name',
        'type',
        'floor_label',
        'description',
        'capacity',
        'sort_order',
        'status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => VenueSpaceType::class,
            'status' => VenueStatus::class,
            'capacity' => 'integer',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(FloorPlan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VenueStatus::Active->value);
    }
}
