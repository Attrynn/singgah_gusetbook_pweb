<?php

namespace App\Models;

use App\Enums\VendorPortalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPortalMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'vendor_id',
        'vendor_contact_id',
        'user_id',
        'invited_email',
        'status',
        'invitation_token_hash',
        'invitation_expires_at',
        'invited_by',
        'accepted_at',
        'revoked_at',
        'revoked_by',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorPortalStatus::class,
            'invitation_expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function vendorContact(): BelongsTo
    {
        return $this->belongsTo(VendorContact::class, 'vendor_contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function engagementAccess(): HasMany
    {
        return $this->hasMany(VendorPortalEngagementAccess::class);
    }

    public function isUsable(): bool
    {
        return $this->status === VendorPortalStatus::Active && $this->accepted_at !== null;
    }

    public function isInvitationExpired(): bool
    {
        return $this->invitation_expires_at !== null && $this->invitation_expires_at->isPast();
    }
}
