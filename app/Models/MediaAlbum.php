<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Single source of truth for album membership: media_assets.media_album_id.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'media_album_id')->orderBy('sort_order');
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

        return $this->assets()->approved()->first()?->url();
    }
}
