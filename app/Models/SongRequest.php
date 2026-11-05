<?php

namespace App\Models;

use App\Enums\SongStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SongRequest extends Model
{
    protected $fillable = ['event_id', 'guest_id', 'song_title', 'artist', 'note', 'status'];

    protected function casts(): array
    {
        return ['status' => SongStatus::class];
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
