<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar_path',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_user')
            ->using(EventUser::class)
            ->withPivot('role_label')
            ->withTimestamps();
    }

    public function guestbookFavorites(): HasMany
    {
        return $this->hasMany(GuestbookFavorite::class);
    }

    public function favoriteMessages(): BelongsToMany
    {
        return $this->belongsToMany(GuestbookMessage::class, 'guestbook_favorites')
            ->withTimestamps();
    }

    public function isSuperadmin(): bool
    {
        return $this->role === UserRole::Superadmin;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::Owner;
    }

    public function canAccessEvent(Event|int $event): bool
    {
        $eventId = $event instanceof Event ? $event->id : $event;

        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->events()->whereKey($eventId)->exists();
    }

    public function eventIds(): array
    {
        return $this->events()->pluck('events.id')->all();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOwners($query)
    {
        return $query->where('role', UserRole::Owner);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
