<?php

namespace Tests\Feature;

use App\Models\GuestbookMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWedding;
use Tests\TestCase;

class LiveWallCursorTest extends TestCase
{
    use BuildsWedding, RefreshDatabase;

    public function test_polling_returns_ascending_batches_without_skipping_or_duplicating(): void
    {
        $event = $this->makeEvent(['live_wall_enabled' => true, 'status' => 'live']);

        for ($i = 0; $i < 35; $i++) {
            GuestbookMessage::factory()->approved()->create([
                'event_id' => $event->id,
                'visibility' => 'public',
            ]);
        }

        $first = $this->getJson('/wall/'.$event->slug.'/messages?since_id=0')->assertOk()->json();
        $firstIds = array_column($first['messages'], 'id');

        $this->assertCount(30, $firstIds);
        $this->assertSame($firstIds, array_values(array_unique($firstIds)));
        $sorted = $firstIds;
        sort($sorted);
        $this->assertSame($sorted, $firstIds, 'Messages must be returned in ascending id order.');

        $second = $this->getJson('/wall/'.$event->slug.'/messages?since_id='.$first['next_cursor'])->assertOk()->json();
        $secondIds = array_column($second['messages'], 'id');

        $this->assertCount(5, $secondIds);
        $this->assertGreaterThan($first['next_cursor'], min($secondIds));
        $this->assertSame([], array_intersect($firstIds, $secondIds));
    }

    public function test_initial_render_exposes_a_cursor_that_matches_the_rendered_messages(): void
    {
        $event = $this->makeEvent(['live_wall_enabled' => true, 'status' => 'live']);

        for ($i = 0; $i < 30; $i++) {
            GuestbookMessage::factory()->approved()->create([
                'event_id' => $event->id,
                'visibility' => 'public',
            ]);
        }

        $response = $this->get('/wall/'.$event->slug)->assertOk();

        $response->assertSee('Live Wall');

        $maxId = GuestbookMessage::query()->max('id');
        $this->assertSame((int) $maxId, (int) $response->viewData('cursor'));
    }
}
