<?php

namespace App\Models;

use App\Enums\VenueStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'address',
        'google_maps_url',
        'latitude',
        'longitude',
        'timezone',
        'status',
        'archived_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => VenueStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(VenueSpace::class)->orderBy('sort_order')->orderBy('name');
    }

    public function activeSpaces(): HasMany
    {
        return $this->spaces()->where('status', VenueStatus::Active->value);
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(FloorPlan::class);
    }

    public function eventVenues(): HasMany
    {
        return $this->hasMany(EventVenue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VenueStatus::Active->value);
    }
}
