<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestbookFavorite extends Model
{
    protected $fillable = ['user_id', 'guestbook_message_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(GuestbookMessage::class, 'guestbook_message_id');
    }
}
