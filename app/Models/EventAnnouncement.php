<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\AnnouncementPriority;
use App\Enums\AnnouncementStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventAnnouncement extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'title',
        'body',
        'priority',
        'audience',
        'audience_ref',
        'status',
        'requires_acknowledgement',
        'published_at',
        'expires_at',
        'withdrawn_at',
        'created_by',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => AnnouncementPriority::class,
            'audience' => AnnouncementAudience::class,
            'status' => AnnouncementStatus::class,
            'audience_ref' => 'array',
            'requires_acknowledgement' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EventAnnouncementRecipient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isVisible(): bool
    {
        if ($this->status !== AnnouncementStatus::Published) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
