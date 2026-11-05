<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSetting extends Model
{
    protected $fillable = ['event_id', 'key', 'value'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function castedValue(): mixed
    {
        return json_decode($this->value, true) ?? $this->value;
    }
}
