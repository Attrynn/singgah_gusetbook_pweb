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
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => EventStaffRole::class,
            'status' => MembershipStatus::class,
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

    public function runOfShowItems(): HasMany
    {
        return $this->hasMany(EventRunOfShowItem::class, 'pic_staff_membership_id');
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    public function isRevoked(): bool
    {
        return $this->status === MembershipStatus::Revoked;
    }
}
