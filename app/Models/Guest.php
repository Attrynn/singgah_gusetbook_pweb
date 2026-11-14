<?php

namespace App\Models;

use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Enums\TokenType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id', 'guest_category_id', 'guest_group_id', 'name', 'email', 'phone',
        'notes', 'status', 'max_attendees', 'rsvp_status', 'checked_in_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => GuestStatus::class,
            'rsvp_status' => RsvpStatus::class,
            'checked_in_at' => 'datetime',
            'max_attendees' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GuestCategory::class, 'guest_category_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(GuestToken::class);
    }

    public function invitationToken(): HasOne
    {
        return $this->hasOne(GuestToken::class)
            ->where('type', TokenType::Invitation->value)
            ->latestOfMany();
    }

    public function qrToken(): HasOne
    {
        return $this->hasOne(GuestToken::class)
            ->where('type', TokenType::Qr->value)
            ->latestOfMany();
    }

    public function invitation(): HasOne
    {
        return $this->hasOne(Invitation::class);
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }

    public function checkin(): HasOne
    {
        return $this->hasOne(Checkin::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GuestbookMessage::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function memoryConsents(): HasMany
    {
        return $this->hasMany(MemoryConsent::class);
    }

    public function songRequests(): HasMany
    {
        return $this->hasMany(SongRequest::class);
    }

    public function pollResponses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    public function gameAttempts(): HasMany
    {
        return $this->hasMany(GameAttempt::class);
    }

    public function tableAssignment(): HasOne
    {
        return $this->hasOne(TableAssignment::class);
    }

    public function scopeForEvent(Builder $query, int $eventId): Builder
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GuestStatus::Active->value);
    }

    public function scopeCheckedIn(Builder $query): Builder
    {
        return $query->whereNotNull('checked_in_at');
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function hasResponded(): bool
    {
        return $this->rsvp_status !== RsvpStatus::Pending;
    }

    public function activeInvitationToken(): ?GuestToken
    {
        return $this->tokens()
            ->where('type', TokenType::Invitation->value)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    public function activeQrToken(): ?GuestToken
    {
        return $this->tokens()
            ->where('type', TokenType::Qr->value)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();
    }

    /**
     * Identified individuals that belong to this invitation party.
     */
    public function members(): HasMany
    {
        return $this->hasMany(GuestPartyMembership::class);
    }

    /**
     * Active identified individuals only.
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('status', 'active');
    }

    /**
     * Household alias for the legacy `guest_groups` grouping table.
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    public function identifiedCount(): int
    {
        return $this->members()->where('status', 'active')->count();
    }

    public function remainingQuota(): int
    {
        return max(0, (int) $this->max_attendees - $this->identifiedCount());
    }
}
