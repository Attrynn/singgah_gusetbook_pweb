<?php

namespace App\Models;

use App\Enums\MemoryItemType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consent record attached to a single memory contribution.
 */
class MemoryConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'contribution_type', 'contribution_id', 'guest_id',
        'guest_party_membership_id', 'contributor_name', 'allow_event_display',
        'allow_public_display', 'terms_version', 'accepted_at', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'allow_event_display' => 'boolean',
            'allow_public_display' => 'boolean',
            'accepted_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(GuestPartyMembership::class, 'guest_party_membership_id');
    }

    /**
     * Resolve the media asset or guestbook message this consent covers.
     */
    public function contribution(): ?Model
    {
        $type = MemoryItemType::tryFrom((string) $this->contribution_type);

        if ($type === null) {
            return null;
        }

        $class = $type->modelClass();

        return $class::query()->find($this->contribution_id);
    }

    /**
     * Public display requires both the event and public permissions.
     */
    public function allowsPublic(): bool
    {
        return $this->allow_public_display && $this->allow_event_display;
    }
}
