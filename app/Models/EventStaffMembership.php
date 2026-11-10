<?php

namespace App\Models;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventStaffMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'status',
        'assigned_by',
        'invited_by',
        'invited_at',
        'accepted_at',
        'declined_at',
        'suspended_at',
        'removed_at',
        'expires_at',
        'notes',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventStaffRole::class,
            'status' => MembershipStatus::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'suspended_at' => 'datetime',
            'removed_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function runOfShowItems(): HasMany
    {
        return $this->hasMany(EventRunOfShowItem::class, 'pic_staff_membership_id');
    }

    public function permissionGrants(): HasMany
    {
        return $this->hasMany(EventStaffPermissionGrant::class);
    }

    public function stationAssignments(): HasMany
    {
        return $this->hasMany(EventStationAssignment::class, 'staff_membership_id');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(EventTask::class, 'assigned_membership_id');
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->status === MembershipStatus::Pending;
    }

    public function isRevoked(): bool
    {
        return $this->status === MembershipStatus::Revoked;
    }

    public function isSuspended(): bool
    {
        return $this->status === MembershipStatus::Suspended;
    }
}
