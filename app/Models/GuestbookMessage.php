<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GuestbookMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id', 'guest_id', 'sender_name', 'message', 'emoji', 'visibility',
        'status', 'is_pinned', 'reviewed_by', 'reviewed_at', 'ip_address',
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
}
