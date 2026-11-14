<?php

namespace App\Models;

use App\Enums\MemoryItemType;
use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestbookMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id', 'guest_id', 'guest_party_membership_id', 'sender_name', 'message', 'emoji',
        'client_submission_id', 'photo_asset_id', 'visibility', 'status', 'is_pinned',
        'reviewed_by', 'reviewed_at', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => MessageVisibility::class,
            'status' => MessageStatus::class,
            'is_pinned' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function guestPartyMembership(): BelongsTo
    {
        return $this->belongsTo(GuestPartyMembership::class, 'guest_party_membership_id');
    }

    public function photoAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_asset_id');
    }

    /**
     * Consent record attached to this message when it is a memory contribution.
     */
    public function consent(): HasOne
    {
        return $this->hasOne(MemoryConsent::class, 'contribution_id')
            ->where('contribution_type', MemoryItemType::GuestbookMessage->value);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guestbook_favorites')->withTimestamps();
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::Approved->value);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::Pending->value);
    }

    public function scopePublicVisible(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::Approved->value)
            ->where('visibility', MessageVisibility::Public->value);
    }

    public function isPublic(): bool
    {
        return $this->visibility === MessageVisibility::Public;
    }

    public function isApproved(): bool
    {
        return $this->status === MessageStatus::Approved;
    }

    /**
     * Visible in the event memory timeline only once approved; pending,
     * rejected and hidden messages are excluded.
     */
    public function isVisibleInMemory(): bool
    {
        return $this->status === MessageStatus::Approved;
    }
}
