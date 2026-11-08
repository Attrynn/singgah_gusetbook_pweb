<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSetupChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'label',
        'description',
        'required',
        'sequence',
        'completed',
        'completed_at',
        'source_key',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'sequence' => 'integer',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
