<?php

namespace App\Models;

use App\Enums\SpatialReferenceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SpatialReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'floor_plan_id',
        'floor_plan_version_id',
        'object_id',
        'object_type',
        'referenceable_type',
        'referenceable_id',
        'label',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SpatialReferenceStatus::class,
        ];
    }

    public function referenceable(): MorphTo
    {
        return $this->morphTo();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SpatialReferenceStatus::Active->value);
    }
}
