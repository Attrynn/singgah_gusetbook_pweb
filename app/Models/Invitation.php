<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = [
        'guest_id', 'event_id', 'guest_token_id', 'status', 'template',
        'sent_at', 'opened_at', 'open_count', 'last_shared_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'last_shared_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(GuestToken::class, 'guest_token_id');
    }

    public function registerOpen(): void
    {
        $this->forceFill([
            'opened_at' => $this->opened_at ?? now(),
            'open_count' => $this->open_count + 1,
            'status' => $this->status === InvitationStatus::NotSent
                ? InvitationStatus::LinkOpened
                : $this->status,
        ])->save();
    }
}
