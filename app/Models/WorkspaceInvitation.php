<?php

namespace App\Models;

use App\Enums\WorkspaceInvitationStatus;
use App\Enums\WorkspaceInvitationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'invited_by',
        'email',
        'type',
        'organization_role',
        'event_assignments',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'accepted_by',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkspaceInvitationType::class,
            'status' => WorkspaceInvitationStatus::class,
            'event_assignments' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === WorkspaceInvitationStatus::Pending;
    }

    public function isUsable(): bool
    {
        return $this->isPending() && ! $this->isExpired();
    }
}
