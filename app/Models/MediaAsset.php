<?php

namespace App\Models;

use App\Enums\MediaStatus;
use App\Enums\MemoryItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class MediaAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id', 'disk', 'media_album_id', 'guest_id', 'guest_party_membership_id',
        'uploaded_by', 'path', 'thumbnail_path', 'medium_path', 'original_name', 'mime_type',
        'size_bytes', 'caption', 'client_submission_id', 'width', 'height', 'checksum',
        'hidden_at', 'sort_order', 'status', 'is_featured', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MediaStatus::class,
            'is_featured' => 'boolean',
            'reviewed_at' => 'datetime',
            'hidden_at' => 'datetime',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
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

    public function guestPartyMembership(): BelongsTo
    {
        return $this->belongsTo(GuestPartyMembership::class, 'guest_party_membership_id');
    }

    /**
     * Consent record attached to this asset when it is a memory contribution.
     */
    public function consent(): HasOne
    {
        return $this->hasOne(MemoryConsent::class, 'contribution_id')
            ->where('contribution_type', MemoryItemType::MediaAsset->value);
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
     *
     * Owner-context URLs carry `context=owner` inside the signature so the
     * delivery endpoint keeps serving the couple on archived or completed
     * events; the flag never changes the URL for public delivery.
     */
    public function url(?string $variant = null, bool $ownerContext = false): string
    {
        // Owner context covers every asset of the couple's own event, including
        // submissions still awaiting moderation; public context only ever
        // resolves approved assets.
        if (! $ownerContext && ! $this->isApproved()) {
            return route('admin.media.preview', $this);
        }

        return URL::temporarySignedRoute(
            'media.show',
            now()->addMinutes(max(1, (int) config('media.delivery_ttl_minutes', 60))),
            array_filter([
                'asset' => $this->getKey(),
                'variant' => $variant,
                'context' => $ownerContext ? 'owner' : null,
            ])
        );
    }

    public function thumbnailUrl(bool $ownerContext = false): string
    {
        return $this->url($this->thumbnail_path ? 'thumb' : null, $ownerContext);
    }

    public function mediumUrl(bool $ownerContext = false): string
    {
        return $this->url($this->medium_path ? 'medium' : null, $ownerContext);
    }

    public function isApproved(): bool
    {
        return $this->status === MediaStatus::Approved;
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * Visible in the event memory gallery when approved and not hidden.
     */
    public function isVisibleInMemory(): bool
    {
        return $this->isApproved() && ! $this->isHidden();
    }
}
