<?php

namespace App\Models;

use App\Enums\TokenType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Admission credential for a single identified individual.
 *
 * Credential handling mirrors the legacy `GuestToken` contract: the raw token
 * is encrypted at rest and only its SHA-256 hash is indexed for lookups.
 */
class GuestPass extends Model
{
    protected $fillable = [
        'guest_party_membership_id', 'event_id', 'type', 'token_hash', 'token_ciphertext',
        'token_prefix', 'expires_at', 'revoked_at', 'last_used_at', 'status', 'created_by',
    ];

    protected $hidden = ['token_hash', 'token_ciphertext'];

    protected function casts(): array
    {
        return [
            'type' => TokenType::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GuestPartyMembership::class, 'guest_party_membership_id');
    }

    public function guestPartyMembership(): BelongsTo
    {
        return $this->member();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public static function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public static function generateRawToken(): string
    {
        return Str::random(64);
    }

    public static function findByRaw(string $rawToken): ?self
    {
        return static::with(['member.guest.event', 'event'])
            ->where('token_hash', static::hashToken($rawToken))
            ->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isValid(): bool
    {
        return $this->status === 'active' && ! $this->isRevoked() && ! $this->isExpired();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn ($builder) => $builder->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function rawToken(): string
    {
        return Crypt::decryptString($this->token_ciphertext);
    }

    public function markUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }
}
