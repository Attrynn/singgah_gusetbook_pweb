<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_qr_scan_creates_checkin(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);
        $qr = $this->qrToken($guest);

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $qr, 'event_id' => $event->id])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('checkins', [
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'method' => 'qr',
        ]);
        $this->assertNotNull($guest->fresh()->checked_in_at);
    }

    public function test_duplicate_scan_is_prevented(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);
        $qr = $this->qrToken($guest);

        $this->actingAs($admin)->postJson(route('admin.checkins.scan'), ['token' => $qr]);
        $second = $this->actingAs($admin)->postJson(route('admin.checkins.scan'), ['token' => $qr]);

        $second->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertSame(1, Checkin::where('guest_id', $guest->id)->count());
    }

    public function test_scan_reports_wrong_event(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $other = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);
        $qr = $this->qrToken($guest);

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $qr, 'event_id' => $other->id])
            ->assertOk()
            ->assertJson(['status' => 'wrong_event']);

        $this->assertDatabaseCount('checkins', 0);
    }

    public function test_invitation_token_is_not_valid_for_checkin(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $invitation = $this->invitationToken($guest);

        $this->actingAs($admin)
            ->postJson(route('admin.checkins.scan'), ['token' => $invitation])
            ->assertOk()
            ->assertJson(['status' => 'invalid']);
    }

    public function test_manual_checkin_works(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['rsvp_status' => 'confirmed']);

        $this->actingAs($admin)
            ->post(route('admin.checkins.manual'), ['guest_id' => $guest->id, 'notes' => 'Meja depan'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('checkins', [
            'guest_id' => $guest->id,
            'method' => 'manual',
        ]);
    }
}
