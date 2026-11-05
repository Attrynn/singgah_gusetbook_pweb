<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameOption extends Model
{
    protected $fillable = ['game_question_id', 'label', 'is_correct', 'sort_order'];

    protected $hidden = ['is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(GameQuestion::class, 'game_question_id');
    }
}
