<?php

namespace App\Models;

use App\Enums\GuestPartyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One identified individual inside an invitation party (`Guest`).
 *
 * The party keeps the legacy quota semantics in `guests.max_attendees`.
 * Unidentified quota slots are deliberately not represented here.
 */
class GuestPartyMembership extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'guest_id', 'event_id', 'name', 'email', 'phone', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => GuestPartyStatus::class,
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function passes(): HasMany
    {
        return $this->hasMany(GuestPass::class);
    }

    public function activePasses(): HasMany
    {
        return $this->passes()
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GuestPartyStatus::Active->value);
    }

    public function isActive(): bool
    {
        return $this->status === GuestPartyStatus::Active;
    }
}
