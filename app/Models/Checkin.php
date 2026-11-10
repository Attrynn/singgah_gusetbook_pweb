<?php

namespace App\Models;

use App\Enums\CheckinMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    protected $fillable = [
        'event_id', 'guest_id', 'guest_party_membership_id', 'guest_pass_id', 'checked_in_by',
        'method', 'checked_in_at', 'notes', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'method' => CheckinMethod::class,
            'checked_in_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function partyMembership(): ?BelongsTo
    {
        return $this->belongsTo(GuestPartyMembership::class, 'guest_party_membership_id');
    }

    public function pass(): ?BelongsTo
    {
        return $this->belongsTo(GuestPass::class, 'guest_pass_id');
    }
}
