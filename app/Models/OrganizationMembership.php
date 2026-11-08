<?php

namespace App\Models;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
        'status',
        'invited_by',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrganizationRole::class,
            'status' => OrganizationMembershipStatus::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isActive(): bool
    {
        return $this->status === OrganizationMembershipStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === OrganizationMembershipStatus::Suspended;
    }

    public function isRevoked(): bool
    {
        return $this->status === OrganizationMembershipStatus::Revoked;
    }

    public function hasRole(OrganizationRole $role): bool
    {
        return $this->role === $role;
    }

    public function hasAtLeastRole(OrganizationRole $minimum): bool
    {
        return $this->role->isAtLeast($minimum);
    }
}
