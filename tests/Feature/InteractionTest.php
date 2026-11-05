<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameAttempt;
use App\Models\Poll;
use App\Models\SongRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class InteractionTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_guest_can_request_a_song(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/song-request', [
                'song_title' => 'Perfect',
                'artist' => 'Ed Sheeran',
                'note' => 'Untuk first dance',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('song_requests', [
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'song_title' => 'Perfect',
            'status' => 'pending',
        ]);
    }

    public function test_guest_can_vote_once_in_a_poll(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $poll = Poll::create(['event_id' => $event->id, 'question' => 'Lagu pertama?', 'is_active' => true]);
        $option = $poll->options()->create(['label' => 'A', 'sort_order' => 0]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/poll/'.$poll->id, ['poll_option_id' => $option->id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('poll_responses', [
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'guest_id' => $guest->id,
        ]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/poll/'.$poll->id, ['poll_option_id' => $option->id])
            ->assertSessionHasErrors('poll');

        $this->assertDatabaseCount('poll_responses', 1);
    }

    public function test_closed_poll_cannot_be_voted(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $poll = Poll::create([
            'event_id' => $event->id,
            'question' => 'Sudah ditutup?',
            'is_active' => true,
            'closes_at' => now()->subHour(),
        ]);
        $option = $poll->options()->create(['label' => 'A', 'sort_order' => 0]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/poll/'.$poll->id, ['poll_option_id' => $option->id])
            ->assertSessionHasErrors('poll');
    }

    public function test_game_submission_is_scored_and_cannot_be_repeated(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $game = Game::create([
            'event_id' => $event->id,
            'title' => 'Quiz',
            'type' => 'quiz',
            'is_active' => true,
        ]);

        $q1 = $game->questions()->create(['question' => 'Q1', 'sort_order' => 0]);
        $correct1 = $q1->options()->create(['label' => 'Benar', 'is_correct' => true, 'sort_order' => 0]);
        $q1->options()->create(['label' => 'Salah', 'is_correct' => false, 'sort_order' => 1]);

        $q2 = $game->questions()->create(['question' => 'Q2', 'sort_order' => 1]);
        $q2->options()->create(['label' => 'Benar', 'is_correct' => true, 'sort_order' => 0]);
        $wrong2 = $q2->options()->create(['label' => 'Salah', 'is_correct' => false, 'sort_order' => 1]);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/game/'.$game->id, [
                'answers' => [
                    $q1->id => $correct1->id,
                    $q2->id => $wrong2->id,
                ],
            ])
            ->assertSessionHasNoErrors();

        $attempt = GameAttempt::where('game_id', $game->id)->where('guest_id', $guest->id)->firstOrFail();
        $this->assertSame(1, $attempt->score);
        $this->assertSame(2, $attempt->total_questions);

        $this->from('/invite/'.$token)
            ->post('/invite/'.$token.'/game/'.$game->id, [
                'answers' => [$q1->id => $correct1->id],
            ])
            ->assertSessionHasErrors('game');

        $this->assertSame(1, GameAttempt::where('guest_id', $guest->id)->count());
    }

    public function test_song_request_is_blocked_for_inactive_event(): void
    {
        $event = $this->makeEvent(['status' => 'draft']);
        $guest = $this->makeGuest($event);
        $token = $this->invitationToken($guest);

        $this->post('/invite/'.$token.'/song-request', ['song_title' => 'Tidak boleh'])
            ->assertForbidden();

        $this->assertSame(0, SongRequest::count());
    }
}
