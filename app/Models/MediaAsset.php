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
        'event_id', 'media_album_id', 'guest_id', 'uploaded_by', 'path', 'thumbnail_path',
        'original_name', 'mime_type', 'size_bytes', 'caption', 'status', 'is_featured',
        'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'is_featured' => 'boolean',
            'reviewed_at' => 'datetime',
            'size_bytes' => 'integer',
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

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return $this->thumbnail_path
            ? Storage::disk('public')->url($this->thumbnail_path)
            : $this->url();
    }

    public function isApproved(): bool
    {
        return $this->status === MediaStatus::Approved;
    }
}
