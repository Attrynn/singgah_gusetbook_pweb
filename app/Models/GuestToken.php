<?php

namespace App\Models;

use App\Enums\TokenType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class GuestToken extends Model
{
    protected $fillable = [
        'guest_id', 'event_id', 'type', 'token_hash', 'token_ciphertext', 'token_prefix',
        'expires_at', 'revoked_at', 'last_used_at',
        'short_code',
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

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
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
        return static::with(['guest.event', 'event'])
            ->where('token_hash', static::hashToken($rawToken))
            ->first();
    }

    public static function generateShortCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('short_code', $code)->exists());

        return $code;
    }

    public static function findByShortCode(string $shortCode): ?self
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $shortCode));

        if (strlen($normalized) !== 8) {
            return null;
        }

        return static::with(['guest.event', 'event'])
            ->where('short_code', $normalized)
            ->first();
    }

    public function formattedShortCode(): ?string
    {
        if (! $this->short_code) {
            return null;
        }

        return substr($this->short_code, 0, 4).'-'.substr($this->short_code, 4);
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
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
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
