<?php

namespace App\Models;

use App\Enums\EventStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'groom_name', 'bride_name', 'groom_profile', 'bride_profile',
        'couple_description', 'cover_image', 'event_date', 'start_time', 'end_time',
        'venue_name', 'venue_address', 'google_maps_url', 'dress_code', 'description',
        'status', 'created_by', 'rsvp_deadline', 'rsvp_enabled', 'guestbook_enabled',
        'gallery_enabled', 'gift_enabled', 'live_wall_enabled', 'timezone',
        'organization_id',
        'published_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'status' => EventStatus::class,
            'rsvp_deadline' => 'datetime',
            'rsvp_enabled' => 'boolean',
            'guestbook_enabled' => 'boolean',
            'gallery_enabled' => 'boolean',
            'gift_enabled' => 'boolean',
            'live_wall_enabled' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (blank($event->slug)) {
                $event->slug = static::uniqueSlug($event->name);
            }
        });
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'wedding';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function hasValidOrganization(): bool
    {
        return $this->organization_id !== null;
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->using(EventUser::class)
            ->withPivot('role_label')
            ->withTimestamps();
    }

    public function staffMemberships(): HasMany
    {
        return $this->hasMany(EventStaffMembership::class);
    }

    public function ownerMemberships(): HasMany
    {
        return $this->hasMany(EventOwnerMembership::class);
    }

    public function hasStaffMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->staffMemberships()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function hasOwnerMember(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->ownerMemberships()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(EventSetting::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(EventVenue::class);
    }

    public function primaryVenue(): HasOne
    {
        return $this->hasOne(EventVenue::class)->where('is_primary', true);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EventSchedule::class)->orderBy('sort_order')->orderBy('start_time');
    }

    public function giftAccounts(): HasMany
    {
        return $this->hasMany(GiftAccount::class)->orderBy('sort_order');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(GuestCategory::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(GuestGroup::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GuestbookMessage::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(MediaAlbum::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    public function seatingTables(): HasMany
    {
        return $this->hasMany(SeatingTable::class);
    }

    public function tableAssignments(): HasMany
    {
        return $this->hasMany(TableAssignment::class);
    }

    public function songRequests(): HasMany
    {
        return $this->hasMany(SongRequest::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class);
    }

    public function setupConfig(): HasOne
    {
        return $this->hasOne(EventSetupConfig::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(EventSetupChecklistItem::class)->orderBy('sequence');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(EventPhase::class)->orderBy('sequence');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)->orderBy('session_date')->orderBy('sequence');
    }

    public function runOfShowItems(): HasMany
    {
        return $this->hasMany(EventRunOfShowItem::class)->orderBy('sequence');
    }

    public function operation(): HasOne
    {
        return $this->hasOne(EventOperation::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(EventStation::class)->orderBy('name');
    }

    public function stationAssignments(): HasMany
    {
        return $this->hasMany(EventStationAssignment::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class)->latest();
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(EventIncident::class)->latest('reported_at');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(EventAnnouncement::class)->latest('published_at');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EventStatus::Published->value,
            EventStatus::Live->value,
            EventStatus::Completed->value,
        ]);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereDate('event_date', '>=', now()->toDateString());
    }

    public function isPublic(): bool
    {
        return $this->status->isPublic();
    }

    public function allowsGuestActions(): bool
    {
        return $this->status->allowsGuestActions();
    }

    public function isLive(): bool
    {
        return $this->status === EventStatus::Live;
    }

    public function rsvpOpen(): bool
    {
        if (! $this->rsvp_enabled || ! $this->allowsGuestActions()) {
            return false;
        }

        return ! $this->rsvp_deadline || $this->rsvp_deadline->isFuture();
    }

    /**
     * IANA timezone for this event, falling back to the application timezone
     * when the stored value is missing or invalid.
     */
    public function timezoneName(): string
    {
        $timezone = $this->timezone ?: config('app.timezone', 'UTC');

        return in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone', 'UTC');
    }

    /**
     * Absolute start instant (UTC). Event date/time input is interpreted in the
     * event timezone, never in the server timezone.
     */
    public function startsAtUtc(): ?CarbonImmutable
    {
        if (! $this->event_date) {
            return null;
        }

        $date = $this->event_date->format('Y-m-d');
        $time = $this->start_time ? substr((string) $this->start_time, 0, 5) : '00:00';

        try {
            $local = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                "{$date} {$time}",
                $this->timezoneName()
            );
        } catch (\Throwable) {
            return null;
        }

        return $local?->utc();
    }

    /**
     * Absolute end instant (UTC), or start + 4 hours when no end time is set.
     */
    public function endsAtUtc(): ?CarbonImmutable
    {
        $start = $this->startsAtUtc();

        if (! $start) {
            return null;
        }

        if (! $this->end_time) {
            return $start->addHours(4);
        }

        $date = $this->event_date->format('Y-m-d');
        $time = substr((string) $this->end_time, 0, 5);

        try {
            $local = CarbonImmutable::createFromFormat(
                'Y-m-d H:i',
                "{$date} {$time}",
                $this->timezoneName()
            );
        } catch (\Throwable) {
            return $start->addHours(4);
        }

        // An end time earlier than the start time is treated as next day.
        if ($local && $local->lessThan($start->setTimezone($this->timezoneName()))) {
            $local = $local->addDay();
        }

        return $local?->utc();
    }

    /**
     * Countdown target as an absolute instant, expressed in the app timezone
     * for consistent Carbon comparisons.
     */
    public function countdownTarget(): ?Carbon
    {
        $utc = $this->startsAtUtc();

        return $utc ? Carbon::instance($utc) : null;
    }

    /**
     * Event start rendered in the event timezone.
     */
    public function localStartsAt(): ?CarbonImmutable
    {
        return $this->startsAtUtc()?->setTimezone($this->timezoneName());
    }

    public function coverUrl(): ?string
    {
        return $this->cover_image ? Storage::disk('public')->url($this->cover_image) : null;
    }

    public function coupleNames(): string
    {
        return $this->groom_name.' & '.$this->bride_name;
    }

    public function publicUrl(?string $token = null): string
    {
        return $token
            ? route('guest.invitation', $token)
            : route('guest.landing', $this->slug);
    }

    public function liveWallUrl(): string
    {
        return route('live-wall.show', $this->slug);
    }

    public function statusLabel(): string
    {
        return $this->status->label();
    }
}
