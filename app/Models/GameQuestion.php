<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameQuestion extends Model
{
    protected $fillable = ['game_id', 'question', 'sort_order'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(GameOption::class)->orderBy('sort_order');
    }
}
