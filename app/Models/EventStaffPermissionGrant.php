<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventStaffPermissionGrant extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_staff_membership_id',
        'permission',
        'granted_by',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(EventStaffMembership::class, 'event_staff_membership_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
