<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'phase_id',
        'name',
        'session_date',
        'start_time',
        'end_time',
        'timezone',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(EventPhase::class, 'phase_id');
    }

    public function runOfShowItems(): HasMany
    {
        return $this->hasMany(EventRunOfShowItem::class, 'event_session_id');
    }
}
