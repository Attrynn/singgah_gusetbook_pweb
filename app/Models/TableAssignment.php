<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableAssignment extends Model
{
    protected $fillable = ['event_id', 'seating_table_id', 'guest_id', 'seat_number'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function seatingTable(): BelongsTo
    {
        return $this->belongsTo(SeatingTable::class, 'seating_table_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
