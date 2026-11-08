<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\PlatformRole;
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
        'email_change_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_change_expires_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'platform_role' => PlatformRole::class,
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

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->using(OrganizationMembership::class)
            ->withTimestamps();
    }

    public function eventStaffMemberships(): HasMany
    {
        return $this->hasMany(EventStaffMembership::class);
    }

    public function eventOwnerMemberships(): HasMany
    {
        return $this->hasMany(EventOwnerMembership::class);
    }

    public function workspaceInvitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class, 'invited_by');
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

    public function isPlatformAdmin(): bool
    {
        return $this->platform_role !== null;
    }

    public function isPlatformSuperadmin(): bool
    {
        return $this->platform_role === PlatformRole::Superadmin;
    }

    public function eventIds(): array
    {
        return $this->events()->pluck('events.id')->all();
    }

    /**
     * Returns the user's active OrganizationMembership for the given org, or null.
     */
    public function organizationMembershipFor(Organization|int $org): ?OrganizationMembership
    {
        $orgId = $org instanceof Organization ? $org->id : $org;

        return $this->organizationMemberships()
            ->where('organization_id', $orgId)
            ->where('status', 'active')
            ->first();
    }

    public function hasOrganizationRole(Organization|int $org, OrganizationRole $role): bool
    {
        $membership = $this->organizationMembershipFor($org);

        return $membership !== null && $membership->hasRole($role);
    }

    public function isWorkspaceOwner(Organization|int $org): bool
    {
        return $this->hasOrganizationRole($org, OrganizationRole::Owner);
    }

    public function isWorkspaceAdmin(Organization|int $org): bool
    {
        $membership = $this->organizationMembershipFor($org);

        return $membership !== null && $membership->hasAtLeastRole(OrganizationRole::Admin);
    }

    public function canAccessEvent(Event|int $event): bool
    {
        if ($this->isSuperadmin() || $this->isPlatformSuperadmin()) {
            return true;
        }

        $eventModel = $event instanceof Event ? $event : Event::find($event);

        if ($eventModel === null) {
            return false;
        }

        $eventId = $eventModel->id;

        // Organization-scoped check: if the event belongs to an organization,
        // the user must be an active organization member, event staff, or event owner.
        if ($eventModel->organization_id !== null) {
            // Active org membership grants access. organizationMembershipFor()
            // only returns ACTIVE memberships, so suspended/revoked members are
            // denied even though an org pivot row exists.
            if ($this->organizationMembershipFor((int) $eventModel->organization_id)?->isActive()) {
                return true;
            }

            // Active event staff membership grants access.
            if ($this->eventStaffMemberships()
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->exists()) {
                return true;
            }

            // Active event owner membership grants access.
            if ($this->eventOwnerMemberships()
                ->where('event_id', $eventId)
                ->where('status', 'active')
                ->exists()) {
                return true;
            }

            return false;
        }

        // Legacy path: direct event_user pivot (event has no organization_id).
        return $this->events()->whereKey($eventId)->exists();
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
