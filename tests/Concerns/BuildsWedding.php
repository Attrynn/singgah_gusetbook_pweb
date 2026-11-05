<?php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\Guest;
use App\Services\InvitationService;

trait BuildsWedding
{
    protected function makeEvent(array $attributes = []): Event
    {
        return Event::factory()->create($attributes);
    }

    protected function makeGuest(Event $event, array $attributes = []): Guest
    {
        return Guest::factory()->create(array_merge([
            'event_id' => $event->id,
        ], $attributes));
    }

    protected function invitationToken(Guest $guest): string
    {
        $service = app(InvitationService::class);

        if (! $guest->activeInvitationToken()) {
            $service->issueForGuest($guest);
            $guest->refresh();
        }

        return $service->rawToken($guest);
    }

    protected function qrToken(Guest $guest): string
    {
        return app(InvitationService::class)->ensureQrToken($guest);
    }
}
