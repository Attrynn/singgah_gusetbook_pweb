<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\AppSetting;
use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\EventVenue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function __construct(
        protected AuditService $audit,
        protected EventLifecycleService $lifecycle,
    ) {}

    public function create(array $data, User $actor): Event
    {
        return DB::transaction(function () use ($data, $actor) {
            $status = EventStatus::tryFrom((string) ($data['status'] ?? EventStatus::Draft->value)) ?? EventStatus::Draft;

            $event = Event::create([
                ...$this->eventPayload($data),
                ...$this->lifecycle->initialPayload($status),
                'created_by' => $actor->id,
            ]);

            $this->syncVenue($event, $data);
            $this->seedDefaultTaxonomies($event);

            if (! empty($data['schedules'])) {
                $this->syncSchedules($event, $data['schedules']);
            }

            if (isset($data['cover'])) {
                $this->storeCover($event, $data['cover']);
            }

            $this->audit->record('event.created', "Event dibuat: {$event->name}", $event, $actor, $event);

            return $event->fresh(['primaryVenue', 'schedules']);
        });
    }

    public function update(Event $event, array $data, User $actor): Event
    {
        return DB::transaction(function () use ($event, $data, $actor) {
            $payload = $this->eventPayload($data);

            if (! empty($data['slug']) && $data['slug'] !== $event->slug) {
                $payload['slug'] = Event::uniqueSlug($data['slug'], $event->id);
            } elseif (empty($data['slug'])) {
                // Never wipe the slug when a partial update omits it.
                unset($payload['slug']);
            }

            if (empty($data['timezone'])) {
                unset($payload['timezone']);
            }

            $event->update($payload);

            if (isset($data['status'])) {
                $status = EventStatus::tryFrom((string) $data['status']);

                if (! $status) {
                    throw ValidationException::withMessages(['status' => 'Status event tidak dikenal.']);
                }

                $this->lifecycle->transition($event, $status, $actor);
            }

            $this->syncVenue($event, $data);

            if (array_key_exists('schedules', $data)) {
                $this->syncSchedules($event, $data['schedules'] ?? []);
            }

            if (isset($data['cover'])) {
                $this->storeCover($event, $data['cover']);
            }

            $this->audit->record('event.updated', "Event diperbarui: {$event->name}", $event, $actor, $event);

            return $event->fresh(['primaryVenue', 'schedules']);
        });
    }

    public function setStatus(Event $event, EventStatus $status, User $actor): Event
    {
        $event = $this->lifecycle->transition($event, $status, $actor);

        $this->audit->record(
            'event.status',
            "Status event {$event->name} diubah menjadi {$status->label()}",
            $event,
            $actor,
            $event,
            ['status' => $status->value]
        );

        return $event->fresh();
    }

    public function delete(Event $event, User $actor): void
    {
        $this->audit->record('event.deleted', "Event dihapus: {$event->name}", $event, $actor, $event);
        $event->delete();
    }

    public function storeCover(Event $event, UploadedFile $file): void
    {
        $disk = config('media.public_disk', 'public');
        $newPath = $file->store("events/{$event->id}/cover", $disk);

        if ($newPath === false) {
            throw new \RuntimeException('Failed to store event cover image.');
        }

        $oldCover = $event->cover_image;

        try {
            $event->update(['cover_image' => $newPath]);
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($newPath);

            throw $e;
        }

        if ($oldCover && $oldCover !== $newPath) {
            Storage::disk($disk)->delete($oldCover);
        }
    }

    protected function eventPayload(array $data): array
    {
        $timezone = $data['timezone'] ?? AppSetting::get('default_timezone', config('app.timezone', 'Asia/Jakarta'));

        return [
            'name' => $data['name'],
            'slug' => $data['slug'] ?? null,
            'groom_name' => $data['groom_name'],
            'bride_name' => $data['bride_name'],
            'groom_profile' => $data['groom_profile'] ?? null,
            'bride_profile' => $data['bride_profile'] ?? null,
            'couple_description' => $data['couple_description'] ?? null,
            'event_date' => $data['event_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'venue_name' => $data['venue_name'],
            'venue_address' => $data['venue_address'],
            'google_maps_url' => $data['google_maps_url'] ?? null,
            'dress_code' => $data['dress_code'] ?? null,
            'description' => $data['description'] ?? null,
            'rsvp_deadline' => $this->toUtc($data['rsvp_deadline'] ?? null, $timezone),
            'rsvp_enabled' => (bool) ($data['rsvp_enabled'] ?? true),
            'guestbook_enabled' => (bool) ($data['guestbook_enabled'] ?? true),
            'gallery_enabled' => (bool) ($data['gallery_enabled'] ?? true),
            'gift_enabled' => (bool) ($data['gift_enabled'] ?? true),
            'live_wall_enabled' => (bool) ($data['live_wall_enabled'] ?? false),
            'timezone' => $timezone,
        ];
    }

    /**
     * Parse a wall-clock date/time value in the event timezone and store the
     * resulting absolute instant in UTC.
     */
    protected function toUtc(?string $value, string $timezone): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function syncVenue(Event $event, array $data): void
    {
        EventVenue::updateOrCreate(
            ['event_id' => $event->id, 'is_primary' => true],
            [
                'name' => $data['venue_name'],
                'address' => $data['venue_address'],
                'google_maps_url' => $data['google_maps_url'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ]
        );
    }

    protected function syncSchedules(Event $event, array $schedules): void
    {
        $existing = $event->schedules()->get()->keyBy('id');
        $keptIds = [];
        $order = 0;

        foreach ($schedules as $schedule) {
            if (blank($schedule['title'] ?? null) || blank($schedule['start_time'] ?? null)) {
                continue;
            }

            $scheduleId = $schedule['id'] ?? null;
            $payload = [
                'event_id' => $event->id,
                'title' => $schedule['title'],
                'description' => $schedule['description'] ?? null,
                'start_time' => $this->toUtc($schedule['start_time'], $event->timezoneName()),
                'end_time' => $this->toUtc($schedule['end_time'] ?? null, $event->timezoneName()),
                'status' => $schedule['status'] ?? 'planned',
                'sort_order' => $order++,
            ];

            if ($scheduleId && $existing->has($scheduleId)) {
                $record = $existing->get($scheduleId);
                $record->update($payload);
                $keptIds[] = $record->id;
            } else {
                $created = EventSchedule::create($payload);
                $keptIds[] = $created->id;
            }
        }

        $query = $event->schedules();
        if (! empty($keptIds)) {
            $query->whereNotIn('id', $keptIds);
        }
        $query->delete();
    }

    public function seedDefaultTaxonomies(Event $event): void
    {
        $categories = [
            ['name' => 'Family', 'slug' => 'family', 'color' => '#8EB4D3'],
            ['name' => 'Friend', 'slug' => 'friend', 'color' => '#A5C7D4'],
            ['name' => 'Colleague', 'slug' => 'colleague', 'color' => '#DFEFED'],
            ['name' => 'Business Relation', 'slug' => 'business-relation', 'color' => '#DCEAF5'],
            ['name' => 'VIP', 'slug' => 'vip', 'color' => '#D5AD70'],
            ['name' => 'Other', 'slug' => 'other', 'color' => '#94A3B8'],
        ];

        foreach ($categories as $category) {
            $event->categories()->firstOrCreate(['slug' => $category['slug']], $category);
        }

        foreach (['Family', 'Friends', 'Colleagues', 'VIP'] as $group) {
            $event->groups()->firstOrCreate(['name' => $group]);
        }
    }
}
