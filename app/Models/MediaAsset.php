<?php

namespace App\Models;

use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id', 'disk', 'media_album_id', 'guest_id', 'uploaded_by', 'path', 'thumbnail_path',
        'medium_path', 'original_name', 'mime_type', 'size_bytes', 'caption', 'sort_order', 'status', 'is_featured',
        'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'is_featured' => 'boolean',
            'reviewed_at' => 'datetime',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(MediaAlbum::class, 'media_album_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Approved->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MediaStatus::Pending->value);
    }

    /**
     * Disk that physically holds the file.
     */
    public function storageDisk(): string
    {
        return $this->disk ?: config('media.public_disk', 'public');
    }

    public function isPublished(): bool
    {
        return $this->isApproved() && $this->storageDisk() === config('media.public_disk', 'public');
    }

    /**
     * Public URL for approved media. Pending/rejected media must be served
     * through an authorised preview endpoint instead.
     */
    public function url(): string
    {
        if ($this->isPublished()) {
            return Storage::disk($this->storageDisk())->url($this->path);
        }

        return route('admin.media.preview', $this);
    }

    public function thumbnailUrl(): string
    {
        if ($this->isPublished() && $this->thumbnail_path) {
            return Storage::disk($this->storageDisk())->url($this->thumbnail_path);
        }

        return $this->url();
    }

    public function mediumUrl(): string
    {
        if ($this->isPublished() && $this->medium_path) {
            return Storage::disk($this->storageDisk())->url($this->medium_path);
        }

        return $this->url();
    }

    public function isApproved(): bool
    {
        return $this->status === MediaStatus::Approved;
    }
}
