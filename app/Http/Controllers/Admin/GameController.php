<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\GameQuestionRequest;
use App\Http\Requests\GameRequest;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameQuestion;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function __construct(protected AuditService $audit)
    {
    }

    public function index(Request $request)
    {
        $query = Game::with('event')->withCount(['questions', 'attempts']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        return view('admin.interactions.games.index', [
            'games' => $query->latest()->paginate(15)->withQueryString(),
            'events' => Event::orderBy('event_date')->get(),
            'filters' => $request->only('event_id'),
        ]);
    }

    public function create()
    {
        return view('admin.interactions.games.create', ['events' => Event::orderBy('event_date')->get()]);
    }

    public function store(GameRequest $request)
    {
        $game = Game::create([
            'event_id' => $request->integer('event_id'),
            'title' => $request->title,
            'description' => $request->description,
            'type' => 'quiz',
            'is_active' => $request->boolean('is_active'),
            'created_by' => $request->user()->id,
        ]);

        $this->audit->record('game.created', "Game dibuat: {$game->title}", $game->event, $request->user(), $game);

        return redirect()->route('admin.interactions.games.edit', $game)->with('status', 'Game dibuat. Tambahkan pertanyaan di bawah.');
    }

    public function edit(Game $game)
    {
        $game->load(['questions.options', 'event'])->loadCount('attempts');

        return view('admin.interactions.games.edit', [
            'game' => $game,
            'events' => Event::orderBy('event_date')->get(),
        ]);
    }

    public function update(GameRequest $request, Game $game)
    {
        $game->update([
            'event_id' => $request->integer('event_id'),
            'title' => $request->title,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->audit->record('game.updated', "Game diperbarui: {$game->title}", $game->event, $request->user(), $game);

        return back()->with('status', 'Game diperbarui.');
    }

    public function destroy(Request $request, Game $game)
    {
        $this->audit->record('game.deleted', "Game dihapus: {$game->title}", $game->event, $request->user(), $game);
        $game->delete();

        return redirect()->route('admin.interactions.games.index')->with('status', 'Game dihapus.');
    }

    public function toggle(Request $request, Game $game)
    {
        $game->update(['is_active' => ! $game->is_active]);
        $this->audit->record('game.toggled', "Status game diubah: {$game->title}", $game->event, $request->user(), $game);

        return back()->with('status', $game->is_active ? 'Game diaktifkan.' : 'Game dinonaktifkan.');
    }

    public function storeQuestion(GameQuestionRequest $request, Game $game)
    {
        DB::transaction(function () use ($request, $game) {
            $question = $game->questions()->create([
                'question' => $request->question,
                'sort_order' => $game->questions()->count(),
            ]);

            foreach (array_values($request->input('options', [])) as $index => $label) {
                $question->options()->create([
                    'label' => $label,
                    'is_correct' => (int) $request->integer('correct_index') === $index,
                    'sort_order' => $index,
                ]);
            }
        });

        $this->audit->record('game.question.created', "Pertanyaan ditambahkan ke {$game->title}", $game->event, $request->user(), $game);

        return back()->with('status', 'Pertanyaan ditambahkan.');
    }

    public function destroyQuestion(Request $request, Game $game, GameQuestion $question)
    {
        abort_unless($question->game_id === $game->id, 404);
        $question->delete();

        return back()->with('status', 'Pertanyaan dihapus.');
    }
}
