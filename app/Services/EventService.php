<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventSchedule;
use App\Models\EventVenue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventService
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function create(array $data, User $actor): Event
    {
        return DB::transaction(function () use ($data, $actor) {
            $event = Event::create([
                ...$this->eventPayload($data),
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
            }

            $event->update($payload);
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
        $event->update([
            'status' => $status,
            'published_at' => in_array($status, [EventStatus::Published, EventStatus::Live], true)
                ? ($event->published_at ?? now())
                : $event->published_at,
            'archived_at' => $status === EventStatus::Archived ? now() : null,
        ]);

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
        if ($event->cover_image) {
            Storage::disk('public')->delete($event->cover_image);
        }

        $path = $file->store("events/{$event->id}/cover", 'public');
        $event->update(['cover_image' => $path]);
    }

    protected function eventPayload(array $data): array
    {
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
            'status' => $data['status'] ?? 'draft',
            'rsvp_deadline' => $data['rsvp_deadline'] ?? null,
            'rsvp_enabled' => (bool) ($data['rsvp_enabled'] ?? true),
            'guestbook_enabled' => (bool) ($data['guestbook_enabled'] ?? true),
            'gallery_enabled' => (bool) ($data['gallery_enabled'] ?? true),
            'gift_enabled' => (bool) ($data['gift_enabled'] ?? true),
            'live_wall_enabled' => (bool) ($data['live_wall_enabled'] ?? false),
            'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
        ];
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
        $event->schedules()->delete();
        $order = 0;

        foreach ($schedules as $schedule) {
            if (blank($schedule['title'] ?? null) || blank($schedule['start_time'] ?? null)) {
                continue;
            }

            EventSchedule::create([
                'event_id' => $event->id,
                'title' => $schedule['title'],
                'description' => $schedule['description'] ?? null,
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'] ?? null,
                'status' => $schedule['status'] ?? 'planned',
                'sort_order' => $order++,
            ]);
        }
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
