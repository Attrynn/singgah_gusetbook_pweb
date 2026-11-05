<?php

namespace Tests\Feature;

use App\Enums\RsvpStatus;
use App\Models\Rsvp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class RsvpTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_guest_can_submit_rsvp(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['max_attendees' => 2]);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/rsvp', [
                'status' => 'confirmed',
                'attendance_count' => 2,
                'companion_names' => 'Budi',
                'dietary_preference' => 'Vegetarian',
                'notes' => 'Datang lebih awal',
            ])
            ->assertRedirect('/invite/'.$token)
            ->assertSessionHasNoErrors();

        $guest->refresh();

        $this->assertSame(RsvpStatus::Confirmed, $guest->rsvp_status);
        $this->assertSame(2, $guest->rsvp->attendance_count);
        $this->assertDatabaseHas('rsvps', [
            'guest_id' => $guest->id,
            'status' => 'confirmed',
            'attendance_count' => 2,
        ]);
    }

    public function test_declined_rsvp_forces_zero_attendance(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->post('/invite/'.$token.'/rsvp', [
            'status' => 'declined',
            'attendance_count' => 3,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Rsvp::where('guest_id', $guest->id)->first()->attendance_count);
    }

    public function test_rsvp_is_rejected_after_deadline(): void
    {
        $event = $this->makeEvent(['rsvp_deadline' => now()->subDay()]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/rsvp', ['status' => 'confirmed', 'attendance_count' => 1])
            ->assertSessionHasErrors('rsvp');

        $this->assertDatabaseMissing('rsvps', ['guest_id' => $guest->id]);
    }

    public function test_rsvp_is_rejected_for_inactive_event(): void
    {
        $event = $this->makeEvent(['status' => 'draft']);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/rsvp', ['status' => 'confirmed', 'attendance_count' => 1])
            ->assertSessionHasErrors('rsvp');
    }

    public function test_confirmed_rsvp_requires_at_least_one_attendee(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/rsvp', ['status' => 'confirmed', 'attendance_count' => 0])
            ->assertSessionHasErrors('attendance_count');
    }
}
