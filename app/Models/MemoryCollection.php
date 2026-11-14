<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A curated, ordered grouping of memory contributions for an event.
 */
class MemoryCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'name', 'description', 'cover_media_asset_id', 'sort_order',
        'is_visible_to_guests', 'archived_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'is_visible_to_guests' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MemoryCollectionItem::class)->orderBy('sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
