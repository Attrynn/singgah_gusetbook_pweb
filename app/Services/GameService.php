<?php

namespace App\Services;

use App\Enums\GuestStatus;
use App\Models\Game;
use App\Models\GameAttempt;
use App\Models\Guest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GameService
{
    public function __construct(protected AuditService $audit) {}

    /**
     * @param  array<int,int>  $answers  question_id => option_id
     */
    public function submit(Game $game, Guest $guest, array $answers): GameAttempt
    {
        $game->loadMissing(['questions.options', 'event']);

        if ($game->event_id !== $guest->event_id) {
            throw ValidationException::withMessages(['game' => 'Permainan bukan milik acara Anda.']);
        }

        if (! $game->event->allowsGuestActions()) {
            throw ValidationException::withMessages(['game' => 'Permainan belum dibuka atau sudah ditutup.']);
        }

        if ($guest->status !== GuestStatus::Active) {
            throw ValidationException::withMessages(['game' => 'Status undangan Anda tidak aktif.']);
        }

        if (! $game->isPlayable()) {
            throw ValidationException::withMessages(['game' => 'Permainan belum aktif.']);
        }

        if (GameAttempt::where('game_id', $game->id)->where('guest_id', $guest->id)->exists()) {
            throw ValidationException::withMessages(['game' => 'Anda sudah menyelesaikan permainan ini.']);
        }

        try {
            return DB::transaction(function () use ($game, $guest, $answers) {
                $score = 0;
                $total = $game->questions->count();

                $attempt = GameAttempt::create([
                    'game_id' => $game->id,
                    'guest_id' => $guest->id,
                    'score' => 0,
                    'total_questions' => $total,
                    'completed_at' => now(),
                ]);

                foreach ($game->questions as $question) {
                    $selectedId = $answers[$question->id] ?? null;
                    $selected = $question->options->firstWhere('id', (int) $selectedId);
                    $isCorrect = $selected && $selected->is_correct;

                    if ($isCorrect) {
                        $score++;
                    }

                    $attempt->answers()->create([
                        'game_question_id' => $question->id,
                        'game_option_id' => $selected?->id,
                        'is_correct' => $isCorrect,
                    ]);
                }

                $attempt->update(['score' => $score]);

                $this->audit->guestAction(
                    $game->event,
                    $guest,
                    'game.completed',
                    "{$guest->name} menyelesaikan {$game->title} dengan skor {$score}/{$total}",
                    ['game_id' => $game->id, 'score' => $score, 'total' => $total]
                );

                return $attempt->fresh(['answers']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['game' => 'Anda sudah menyelesaikan permainan ini.']);
        }
    }
}
