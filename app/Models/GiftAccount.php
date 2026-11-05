<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GiftAccount extends Model
{
    protected $fillable = [
        'event_id', 'bank_name', 'account_number', 'account_owner',
        'qris_path', 'gift_address', 'note', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function qrisUrl(): ?string
    {
        return $this->qris_path ? Storage::disk('public')->url($this->qris_path) : null;
    }
}
