<?php

namespace App\Models;

use App\Enums\RsvpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    protected $fillable = [
        'event_id', 'guest_id', 'status', 'attendance_count', 'companion_names',
        'dietary_preference', 'notes', 'responded_at', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'status' => RsvpStatus::class,
            'attendance_count' => 'integer',
            'responded_at' => 'datetime',
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
}
