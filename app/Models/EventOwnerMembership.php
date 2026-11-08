<?php

namespace App\Models;

use App\Enums\EventOwnerRole;
use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventOwnerMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'role_label',
        'status',
        'invited_by',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'role_label' => EventOwnerRole::class,
            'status' => MembershipStatus::class,
            'accepted_at' => 'datetime',
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

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
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
