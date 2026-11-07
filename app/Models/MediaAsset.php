<?php

namespace App\Models;

use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

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
     * Disk that physically holds the file. Guest media is stored on the
     * private disk; the public disk is only used for staff artefacts.
     */
    public function storageDisk(): string
    {
        return $this->disk ?: config('media.private_disk', 'local');
    }

    /**
     * Approval makes an asset eligible for delivery to the event audience.
     * It never makes the physical object publicly addressable.
     */
    public function isPublished(): bool
    {
        return $this->isApproved();
    }

    /**
     * Signed, short-lived delivery URL. Non-approved assets are only reachable
     * through the superadmin preview endpoint.
     */
    public function url(?string $variant = null): string
    {
        if (! $this->isApproved()) {
            return route('admin.media.preview', $this);
        }

        return URL::temporarySignedRoute(
            'media.show',
            now()->addMinutes(max(1, (int) config('media.delivery_ttl_minutes', 60))),
            array_filter(['asset' => $this->getKey(), 'variant' => $variant])
        );
    }

    public function thumbnailUrl(): string
    {
        return $this->url($this->thumbnail_path ? 'thumb' : null);
    }

    public function mediumUrl(): string
    {
        return $this->url($this->medium_path ? 'medium' : null);
    }

    public function isApproved(): bool
    {
        return $this->status === MediaStatus::Approved;
    }
}
