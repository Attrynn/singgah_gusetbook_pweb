<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class MediaAlbum extends Model
{
    protected $fillable = ['event_id', 'name', 'description', 'cover_media_asset_id', 'created_by'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(MediaAsset::class, 'media_album_items')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(MediaAlbumItem::class);
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_asset_id');
    }

    public function coverUrl(): ?string
    {
        if ($this->cover) {
            return $this->cover->url();
        }

        $first = $this->assets()->first();

        return $first?->url();
    }
}
