<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Guest\Concerns\ResolvesGuest;
use App\Http\Requests\Guest\GuestGameRequest;
use App\Models\Game;
use App\Models\GameAttempt;
use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestGameController extends Controller
{
    use ResolvesGuest;

    public function __construct(protected GameService $games) {}

    public function show(Request $request, string $token, Game $game)
    {
        $guest = $this->guest($request);
        $event = $this->event($request);

        abort_unless($game->event_id === $event->id, 404);

        $attempt = GameAttempt::where('game_id', $game->id)->where('guest_id', $guest->id)->first();
        $game->load(['questions.options' => fn ($q) => $q->orderBy('sort_order')]);

        if ($attempt) {
            $game->questions->each(function ($question) {
                $question->options->each->makeVisible('is_correct');
            });
        }

        return view('guest.game', compact('event', 'guest', 'game', 'attempt'));
    }

    public function submit(GuestGameRequest $request, string $token, Game $game)
    {
        $guest = $this->guest($request);
        $event = $this->event($request);

        abort_unless($game->event_id === $event->id, 404);

        $answers = collect($request->input('answers', []))
            ->mapWithKeys(fn ($value, $key) => [(int) $key => (int) $value])
            ->all();

        $attempt = $this->games->submit($game, $guest, $answers);

        return redirect()
            ->route('guest.game.show', [$token, $game])
            ->with('status', "Skor Anda: {$attempt->score}/{$attempt->total_questions}.");
    }
}
