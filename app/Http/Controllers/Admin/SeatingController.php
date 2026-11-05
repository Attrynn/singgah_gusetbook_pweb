<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\SeatingAssignRequest;
use App\Http\Requests\SeatingTableRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\SeatingTable;
use App\Models\TableAssignment;
use App\Services\SeatingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class SeatingController extends Controller
{
    public function __construct(protected SeatingService $seating) {}

    public function index(Request $request)
    {
        $events = Event::orderBy('event_date')->get();
        $event = $request->integer('event_id')
            ? $events->firstWhere('id', $request->integer('event_id'))
            : $events->first();

        $tables = collect();
        $unassigned = collect();
        $stats = ['tables' => 0, 'capacity' => 0, 'seated' => 0];

        if ($event) {
            $tables = $event->seatingTables()
                ->with(['assignments.guest'])
                ->withCount('assignments')
                ->orderBy('name')
                ->get();

            $unassigned = $event->guests()
                ->whereDoesntHave('tableAssignment')
                ->orderBy('name')
                ->get();

            $stats = [
                'tables' => $tables->count(),
                'capacity' => (int) $tables->sum('capacity'),
                'seated' => (int) $tables->sum('assignments_count'),
            ];
        }

        return view('admin.seating.index', compact('events', 'event', 'tables', 'unassigned', 'stats'));
    }

    public function storeTable(SeatingTableRequest $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));

        if ($event->seatingTables()->where('name', $request->name)->exists()) {
            throw ValidationException::withMessages(['name' => 'Nama meja sudah digunakan pada event ini.']);
        }

        $event->seatingTables()->create($request->validated());

        return back()->with('status', 'Meja ditambahkan.');
    }

    public function updateTable(SeatingTableRequest $request, SeatingTable $table)
    {
        if ($request->has('event_id') && $request->integer('event_id') !== $table->event_id && $table->assignments()->exists()) {
            throw ValidationException::withMessages([
                'event_id' => 'Meja tidak dapat dipindahkan ke acara lain karena sudah memiliki tamu terdaftar.',
            ]);
        }

        $occupied = $table->assignments()->count();

        if ($request->integer('capacity') < $occupied) {
            throw ValidationException::withMessages([
                'capacity' => "Kapasitas tidak boleh lebih kecil dari jumlah tamu terisi ({$occupied}).",
            ]);
        }

        $table->update($request->validated());

        return back()->with('status', 'Meja diperbarui.');
    }

    public function destroyTable(SeatingTable $table)
    {
        $table->delete();

        return back()->with('status', 'Meja dihapus.');
    }

    public function assign(SeatingAssignRequest $request)
    {
        $table = SeatingTable::findOrFail($request->integer('seating_table_id'));
        $guest = Guest::findOrFail($request->integer('guest_id'));

        $this->seating->assign($table, $guest, $request->input('seat_number'), $request->user());

        return back()->with('status', 'Tamu ditempatkan di meja '.$table->name.'.');
    }

    public function unassign(Request $request, TableAssignment $assignment)
    {
        $this->seating->remove($assignment, $request->user());

        return back()->with('status', 'Penempatan tamu dihapus.');
    }
}
