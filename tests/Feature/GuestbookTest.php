<?php

namespace Tests\Feature;

use App\Models\GuestbookMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class GuestbookTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_guest_can_submit_a_wish(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event, ['name' => 'Sinta']);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/guestbook', [
                'message' => 'Selamat menempuh hidup baru!',
                'emoji' => '🤍',
                'visibility' => 'public',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guestbook_messages', [
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'sender_name' => 'Sinta',
            'status' => 'pending',
            'visibility' => 'public',
        ]);
    }

    public function test_message_must_be_long_enough(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/guestbook', [
                'message' => 'ok',
                'visibility' => 'public',
            ])
            ->assertSessionHasErrors('message');

        $this->assertDatabaseCount('guestbook_messages', 0);
    }

    public function test_guestbook_can_be_disabled(): void
    {
        $event = $this->makeEvent(['guestbook_enabled' => false]);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/guestbook', [
                'message' => 'Pesan yang seharusnya ditolak',
                'visibility' => 'public',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_approve_a_message(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $message = GuestbookMessage::factory()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.guestbook.moderate', $message), ['action' => 'approve'])
            ->assertSessionHasNoErrors();

        $message->refresh();
        $this->assertSame('approved', $message->status->value);
        $this->assertNotNull($message->reviewed_at);
    }

    public function test_live_wall_only_shows_approved_public_messages(): void
    {
        $event = $this->makeEvent(['live_wall_enabled' => true]);
        $guest = $this->makeGuest($event);

        GuestbookMessage::factory()->approved()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'message' => 'Pesan yang tampil',
        ]);
        GuestbookMessage::factory()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'message' => 'Pesan yang belum disetujui',
        ]);

        $this->get(route('live-wall.messages', $event->slug))
            ->assertOk()
            ->assertSee('Pesan yang tampil')
            ->assertDontSee('Pesan yang belum disetujui');
    }
}
