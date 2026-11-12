<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPortalEngagementAccess extends Model
{
    use HasFactory;

    protected $table = 'vendor_portal_engagement_access';

    protected $fillable = [
        'vendor_portal_membership_id',
        'event_vendor_engagement_id',
        'granted_at',
        'granted_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(VendorPortalMembership::class, 'vendor_portal_membership_id');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(EventVendorEngagement::class, 'event_vendor_engagement_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
