<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class GuestCapabilityTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_draft_event_invitation_is_not_accessible_with_a_token(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Draft]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->get('/invite/'.$token)->assertForbidden();
    }

    public function test_completed_event_allows_viewing_but_closes_rsvp(): void
    {
        $event = $this->makeEvent(['status' => EventStatus::Completed]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->get('/invite/'.$token)->assertOk();

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/rsvp', ['status' => 'confirmed', 'attendance_count' => 1])
            ->assertForbidden();
    }

    public function test_disabled_gallery_is_not_reachable_by_direct_request(): void
    {
        $event = $this->makeEvent(['gallery_enabled' => false]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->get('/invite/'.$token.'/gallery')->assertForbidden();
    }

    public function test_blocked_guest_cannot_open_invitation(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['status' => 'blocked']);
        $token = $this->invitationToken($guest);

        $this->get('/invite/'.$token)->assertForbidden();
    }
}
