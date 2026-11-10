<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventIncidentUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_incident_id',
        'actor_id',
        'type',
        'from_status',
        'to_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(EventIncident::class, 'event_incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
