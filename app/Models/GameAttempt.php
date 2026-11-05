<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameAttempt extends Model
{
    protected $fillable = ['game_id', 'guest_id', 'score', 'total_questions', 'completed_at'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'score' => 'integer',
            'total_questions' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }

    public function percentage(): int
    {
        return $this->total_questions > 0
            ? (int) round(($this->score / $this->total_questions) * 100)
            : 0;
    }
}
