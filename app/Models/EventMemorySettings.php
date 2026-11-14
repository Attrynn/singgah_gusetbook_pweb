<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-event memory configuration. Exactly one row exists per event; use
 * {@see self::for()} to resolve it lazily with the migration defaults.
 */
class EventMemorySettings extends Model
{
    use HasFactory;

    protected $table = 'event_memory_settings';

    protected $fillable = [
        'event_id', 'submissions_open', 'post_event_submissions', 'submission_opens_at',
        'submission_closes_at', 'auto_approve', 'public_sharing', 'share_token_hash',
        'share_token_prefix', 'share_token_expires_at', 'share_token_revoked_at',
        'closed_at', 'closed_by', 'archived_at', 'retention_days',
    ];

    protected function casts(): array
    {
        return [
            'submissions_open' => 'boolean',
            'post_event_submissions' => 'boolean',
            'auto_approve' => 'boolean',
            'public_sharing' => 'boolean',
            'submission_opens_at' => 'datetime',
            'submission_closes_at' => 'datetime',
            'share_token_expires_at' => 'datetime',
            'share_token_revoked_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'retention_days' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Resolve the settings row for an event, creating it with the migration
     * defaults on first access.
     */
    public static function for(Event $event): self
    {
        $settings = static::firstOrCreate(['event_id' => $event->id]);

        // Eloquent does not re-read database defaults after an insert, so a
        // freshly created row would expose NULL booleans and read as "closed".
        return $settings->wasRecentlyCreated ? $settings->refresh() : $settings;
    }

    /**
     * Read-only resolution used on request paths that must not write. Returns an
     * unsaved instance carrying the migration defaults when the event has never
     * configured memory settings, so legacy events keep their current behaviour.
     */
    public static function resolve(Event $event): self
    {
        return static::query()->where('event_id', $event->id)->first()
            ?? new static([
                'event_id' => $event->id,
                'submissions_open' => true,
                'post_event_submissions' => false,
                'auto_approve' => false,
                'public_sharing' => false,
            ]);
    }

    /**
     * The public share link is usable only while sharing is enabled, a token
     * has been issued, it has not been revoked, and it has not expired.
     */
    public function sharingActive(): bool
    {
        if (! $this->public_sharing || blank($this->share_token_hash) || $this->share_token_revoked_at !== null) {
            return false;
        }

        return $this->share_token_expires_at === null || $this->share_token_expires_at->isFuture();
    }

    /**
     * Submissions are closed when the manual toggle is off, the memory has been
     * closed, or the configured window has not opened / already elapsed.
     */
    public function submissionsClosed(): bool
    {
        if (! $this->submissions_open || $this->closed_at !== null) {
            return true;
        }

        if ($this->submission_opens_at !== null && $this->submission_opens_at->isFuture()) {
            return true;
        }

        return $this->submission_closes_at !== null && $this->submission_closes_at->isPast();
    }
}
