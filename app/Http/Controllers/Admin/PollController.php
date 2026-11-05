<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PollRequest;
use App\Models\Event;
use App\Models\Poll;
use App\Services\AuditService;
use App\Services\PollService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class PollController extends Controller
{
    public function __construct(
        protected PollService $polls,
        protected AuditService $audit,
    ) {
    }

    public function index(Request $request)
    {
        $query = Poll::with(['event', 'options'])
            ->withCount('responses');

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        $polls = $query->latest()->paginate(15)->withQueryString();

        return view('admin.interactions.polls.index', [
            'polls' => $polls,
            'events' => Event::orderBy('event_date')->get(),
            'filters' => $request->only('event_id'),
        ]);
    }

    public function create()
    {
        return view('admin.interactions.polls.create', ['events' => Event::orderBy('event_date')->get()]);
    }

    public function store(PollRequest $request)
    {
        $poll = DB::transaction(function () use ($request) {
            $poll = Poll::create([
                'event_id' => $request->integer('event_id'),
                'question' => $request->question,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active'),
                'show_results' => $request->boolean('show_results'),
                'closes_at' => $request->input('closes_at'),
                'created_by' => $request->user()->id,
            ]);

            foreach (array_values($request->input('options', [])) as $index => $label) {
                $poll->options()->create(['label' => $label, 'sort_order' => $index]);
            }

            return $poll;
        });

        $this->audit->record('poll.created', "Polling dibuat: {$poll->question}", $poll->event, $request->user(), $poll);

        return redirect()->route('admin.interactions.polls.index')->with('status', 'Polling dibuat.');
    }

    public function edit(Poll $poll)
    {
        $poll->load(['options', 'event'])->loadCount('responses');

        return view('admin.interactions.polls.edit', [
            'poll' => $poll,
            'events' => Event::orderBy('event_date')->get(),
        ]);
    }

    public function update(PollRequest $request, Poll $poll)
    {
        DB::transaction(function () use ($request, $poll) {
            $poll->update([
                'event_id' => $request->integer('event_id'),
                'question' => $request->question,
                'description' => $request->description,
                'is_active' => $request->boolean('is_active'),
                'show_results' => $request->boolean('show_results'),
                'closes_at' => $request->input('closes_at'),
            ]);

            $labels = array_values($request->input('options', []));

            if ($poll->responses()->exists()) {
                foreach ($poll->options()->orderBy('sort_order')->get()->values() as $index => $option) {
                    if (isset($labels[$index])) {
                        $option->update(['label' => $labels[$index]]);
                    }
                }
            } else {
                $poll->options()->delete();
                foreach ($labels as $index => $label) {
                    $poll->options()->create(['label' => $label, 'sort_order' => $index]);
                }
            }
        });

        $this->audit->record('poll.updated', "Polling diperbarui: {$poll->question}", $poll->event, $request->user(), $poll);

        return redirect()->route('admin.interactions.polls.index')->with('status', 'Polling diperbarui.');
    }

    public function destroy(Request $request, Poll $poll)
    {
        $this->audit->record('poll.deleted', "Polling dihapus: {$poll->question}", $poll->event, $request->user(), $poll);
        $poll->delete();

        return back()->with('status', 'Polling dihapus.');
    }

    public function toggle(Request $request, Poll $poll)
    {
        $poll->update(['is_active' => ! $poll->is_active]);
        $this->audit->record('poll.toggled', "Status polling diubah: {$poll->question}", $poll->event, $request->user(), $poll);

        return back()->with('status', $poll->is_active ? 'Polling diaktifkan.' : 'Polling dinonaktifkan.');
    }
}
