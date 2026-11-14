<?php

namespace Tests\Feature\M9\Concerns;

use App\Enums\MediaStatus;
use App\Enums\MessageStatus;
use App\Enums\MessageVisibility;
use App\Models\Event;
use App\Models\EventMemorySettings;
use App\Models\Guest;
use App\Models\GuestbookMessage;
use App\Models\MediaAsset;
use App\Models\MemoryCollection;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared fixtures for the Milestone 9 memory suite.
 *
 * Every helper is event-scoped so a test can build two tenants and prove that
 * a lookup from the wrong event returns 404 rather than leaking a record.
 */
trait BuildsMemory
{
    protected function memorySettings(Event $event, array $attributes = []): EventMemorySettings
    {
        $settings = EventMemorySettings::for($event);

        if ($attributes !== []) {
            $settings->update($attributes);
            $settings->refresh();
        }

        return $settings;
    }

    protected function approvedPhoto(Event $event, ?Guest $guest = null, array $attributes = []): MediaAsset
    {
        return $this->photo($event, $guest, array_merge([
            'status' => MediaStatus::Approved,
        ], $attributes));
    }

    protected function pendingPhoto(Event $event, ?Guest $guest = null, array $attributes = []): MediaAsset
    {
        return $this->photo($event, $guest, array_merge([
            'status' => MediaStatus::Pending,
        ], $attributes));
    }

    protected function photo(Event $event, ?Guest $guest = null, array $attributes = []): MediaAsset
    {
        $uuid = (string) Str::uuid();

        return MediaAsset::create(array_merge([
            'event_id' => $event->id,
            'guest_id' => $guest?->id,
            'disk' => 'local',
            'path' => "events/{$event->id}/memory/{$uuid}.jpg",
            'original_name' => 'momen.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 2048,
            'width' => 1200,
            'height' => 800,
            'checksum' => hash('sha256', $uuid),
            'caption' => 'Momen indah',
            'status' => MediaStatus::Pending,
        ], $attributes));
    }

    protected function approvedMessage(Event $event, ?Guest $guest = null, array $attributes = []): GuestbookMessage
    {
        return $this->message($event, $guest, array_merge([
            'status' => MessageStatus::Approved,
        ], $attributes));
    }

    protected function pendingMessage(Event $event, ?Guest $guest = null, array $attributes = []): GuestbookMessage
    {
        return $this->message($event, $guest, array_merge([
            'status' => MessageStatus::Pending,
        ], $attributes));
    }

    protected function message(Event $event, ?Guest $guest = null, array $attributes = []): GuestbookMessage
    {
        return GuestbookMessage::create(array_merge([
            'event_id' => $event->id,
            'guest_id' => $guest?->id,
            'sender_name' => $guest?->name ?? 'Tamu',
            'message' => 'Selamat menempuh hidup baru.',
            'visibility' => MessageVisibility::Public,
            'status' => MessageStatus::Pending,
        ], $attributes));
    }

    protected function collection(Event $event, array $attributes = []): MemoryCollection
    {
        return MemoryCollection::create(array_merge([
            'event_id' => $event->id,
            'name' => 'Momen Keluarga',
            'sort_order' => 0,
            'is_visible_to_guests' => false,
        ], $attributes));
    }

    /**
     * Create a user with the legacy owner role and attach it to the event the
     * same way the invitation flow does.
     */
    protected function ownerUser(Event $event): User
    {
        $user = User::factory()->create();

        $user->events()->syncWithoutDetaching([
            $event->id => ['role_label' => 'groom'],
        ]);

        return $user;
    }
}
