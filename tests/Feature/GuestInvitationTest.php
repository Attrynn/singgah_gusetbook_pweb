<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Game;
use App\Models\Guest;
use App\Models\GuestToken;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class GuestInvitationTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_valid_token_renders_personal_invitation(): void
    {
        $event = $this->makeEvent(['groom_name' => 'Raka', 'bride_name' => 'Alya']);
        $guest = $this->makeGuest($event, ['name' => 'Tamu Istimewa']);
        $token = $this->invitationToken($guest);

        $this->get('/invite/'.$token)
            ->assertOk()
            ->assertSee('Tamu Istimewa')
            ->assertSee('Raka')
            ->assertSee('Alya');
    }

    public function test_unknown_token_returns_not_found(): void
    {
        $this->get('/invite/does-not-exist')->assertNotFound();
    }

    public function test_revoked_token_returns_not_found(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        GuestToken::query()->update(['revoked_at' => now()]);

        $this->get('/invite/'.$token)->assertNotFound();
    }

    public function test_expired_token_returns_not_found(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        GuestToken::query()->update(['expires_at' => now()->subDay()]);

        $this->get('/invite/'.$token)->assertNotFound();
    }

    public function test_token_only_exposes_its_own_event(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $guest = $this->makeGuest($eventA);
        $token = $this->invitationToken($guest);

        $gameB = Game::create([
            'event_id' => $eventB->id,
            'title' => 'Game Event Lain',
            'type' => 'quiz',
            'is_active' => true,
        ]);

        $this->get('/invite/'.$token.'/game/'.$gameB->id)->assertNotFound();
    }

    public function test_poll_from_another_event_cannot_be_voted(): void
    {
        $eventA = $this->makeEvent();
        $eventB = $this->makeEvent();
        $guest = $this->makeGuest($eventA);
        $token = $this->invitationToken($guest);

        $pollB = Poll::create([
            'event_id' => $eventB->id,
            'question' => 'Pertanyaan event lain',
            'is_active' => true,
        ]);
        $option = $pollB->options()->create(['label' => 'Opsi', 'sort_order' => 0]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/poll/'.$pollB->id, ['poll_option_id' => $option->id])
            ->assertSessionHasErrors('poll');
    }

    public function test_admin_can_preview_invitation(): void
    {
        $admin = User::factory()->superadmin()->create();
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->actingAs($admin)
            ->get(route('admin.invitations.preview', $guest))
            ->assertOk()
            ->assertSee('Preview mode');
    }
}
