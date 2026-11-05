<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAnswer extends Model
{
    protected $fillable = ['game_attempt_id', 'game_question_id', 'game_option_id', 'is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(GameAttempt::class, 'game_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(GameQuestion::class, 'game_question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(GameOption::class, 'game_option_id');
    }
}
