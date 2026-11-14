<?php

namespace App\Models;

use App\Enums\FloorPlanVersionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FloorPlanVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'floor_plan_id',
        'version_number',
        'status',
        'schema_version',
        'canvas_width',
        'canvas_height',
        'unit',
        'grid_size',
        'grid_visible',
        'snap_enabled',
        'background_color',
        'background_image_path',
        'background_image_disk',
        'document',
        'object_count',
        'table_count',
        'chair_count',
        'zone_count',
        'seat_capacity',
        'lock_version',
        'created_by',
        'published_by',
        'published_at',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FloorPlanVersionStatus::class,
            'document' => 'array',
            'schema_version' => 'integer',
            'version_number' => 'integer',
            'canvas_width' => 'float',
            'canvas_height' => 'float',
            'grid_size' => 'float',
            'grid_visible' => 'boolean',
            'snap_enabled' => 'boolean',
            'object_count' => 'integer',
            'table_count' => 'integer',
            'chair_count' => 'integer',
            'zone_count' => 'integer',
            'seat_capacity' => 'integer',
            'lock_version' => 'integer',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function floorPlan(): BelongsTo
    {
        return $this->belongsTo(FloorPlan::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function seatingAssignments(): HasMany
    {
        return $this->hasMany(SeatingAssignment::class);
    }

    public function spatialReferences(): HasMany
    {
        return $this->hasMany(SpatialReference::class);
    }

    public function isDraft(): bool
    {
        return $this->status === FloorPlanVersionStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === FloorPlanVersionStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->status === FloorPlanVersionStatus::Archived;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'status' => $this->status->value,
            'object_count' => $this->object_count,
            'table_count' => $this->table_count,
            'chair_count' => $this->chair_count,
            'zone_count' => $this->zone_count,
            'seat_capacity' => $this->seat_capacity,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
