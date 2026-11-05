<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GuestStatus;
use App\Enums\RsvpStatus;
use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Requests\GuestBulkRequest;
use App\Http\Requests\GuestImportRequest;
use App\Http\Requests\GuestRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestCategory;
use App\Models\GuestGroup;
use App\Services\GuestService;
use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GuestController extends Controller
{
    use ExportsCsv;

    public function __construct(
        protected GuestService $guests,
        protected InvitationService $invitations,
    ) {
    }

    public function index(Request $request)
    {
        $query = Guest::query()->with(['event', 'category', 'group', 'invitation', 'rsvp']);

        if ($eventId = $request->integer('event_id')) {
            $query->where('event_id', $eventId);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        foreach (['guest_category_id', 'guest_group_id'] as $filter) {
            if ($value = $request->integer($filter)) {
                $query->where($filter, $value);
            }
        }

        if ($rsvp = $request->string('rsvp_status')->value()) {
            $query->where('rsvp_status', $rsvp);
        }

        if ($attendance = $request->string('attendance')->value()) {
            $attendance === 'checked_in'
                ? $query->whereNotNull('checked_in_at')
                : $query->whereNull('checked_in_at');
        }

        if ($invitation = $request->string('invitation_status')->value()) {
            $query->whereHas('invitation', fn ($q) => $q->where('status', $invitation));
        }

        $sort = $request->string('sort', 'name')->value();
        $direction = $request->string('direction', 'asc')->value() === 'desc' ? 'desc' : 'asc';
        $sortable = ['name', 'created_at', 'rsvp_status', 'checked_in_at'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'name', $direction);

        $eventId = $request->integer('event_id');

        return view('admin.guests.index', [
            'guests' => $query->paginate(20)->withQueryString(),
            'events' => Event::orderBy('event_date')->get(),
            'categories' => $eventId ? GuestCategory::where('event_id', $eventId)->get() : GuestCategory::all(),
            'groups' => $eventId ? GuestGroup::where('event_id', $eventId)->get() : GuestGroup::all(),
            'filters' => $request->only(['event_id', 'search', 'guest_category_id', 'guest_group_id', 'rsvp_status', 'attendance', 'invitation_status', 'sort', 'direction']),
            'rsvpStatuses' => RsvpStatus::cases(),
            'statuses' => GuestStatus::cases(),
        ]);
    }

    public function create(Request $request)
    {
        $eventId = $request->integer('event_id') ?: Event::orderBy('event_date')->value('id');

        return view('admin.guests.create', [
            'guest' => new Guest(['event_id' => $eventId, 'max_attendees' => 2, 'status' => GuestStatus::Active, 'rsvp_status' => RsvpStatus::Pending]),
            'events' => Event::orderBy('event_date')->get(),
            'categories' => $eventId ? GuestCategory::where('event_id', $eventId)->get() : collect(),
            'groups' => $eventId ? GuestGroup::where('event_id', $eventId)->get() : collect(),
            'statuses' => GuestStatus::cases(),
        ]);
    }

    public function store(GuestRequest $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $guest = $this->guests->create($event, $request->validated(), $request->user());

        return redirect()->route('admin.guests.show', $guest)
            ->with('status', 'Tamu ditambahkan dan tautan undangan personal berhasil dibuat.');
    }

    public function show(Guest $guest)
    {
        $guest->load([
            'event', 'category', 'group', 'rsvp', 'checkin.officer', 'invitation.token',
            'tableAssignment.seatingTable', 'messages', 'mediaAssets', 'songRequests',
        ]);

        $activities = $guest->event->activities()
            ->where('guest_id', $guest->id)
            ->latest()
            ->take(20)
            ->get();

        $invitationUrl = $this->invitations->invitationUrl($guest);

        return view('admin.guests.show', compact('guest', 'activities', 'invitationUrl'));
    }

    public function edit(Guest $guest)
    {
        $guest->load('event');

        return view('admin.guests.edit', [
            'guest' => $guest,
            'events' => Event::orderBy('event_date')->get(),
            'categories' => GuestCategory::where('event_id', $guest->event_id)->get(),
            'groups' => GuestGroup::where('event_id', $guest->event_id)->get(),
            'statuses' => GuestStatus::cases(),
        ]);
    }

    public function update(GuestRequest $request, Guest $guest)
    {
        $this->guests->update($guest, $request->validated(), $request->user());

        return redirect()->route('admin.guests.show', $guest)->with('status', 'Data tamu diperbarui.');
    }

    public function destroy(Guest $guest)
    {
        $this->guests->delete($guest, request()->user());

        return redirect()->route('admin.guests.index')->with('status', 'Tamu dihapus.');
    }

    public function importForm()
    {
        return view('admin.guests.import', ['events' => Event::orderBy('event_date')->get()]);
    }

    public function import(GuestImportRequest $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));
        $result = $this->guests->importCsv($event, $request->file('file')->getRealPath(), $request->user());

        return redirect()->route('admin.guests.index', ['event_id' => $event->id])
            ->with('status', "Import selesai: {$result['imported']} tamu ditambahkan, {$result['skipped']} dilewati.")
            ->with('import_errors', $result['errors']);
    }

    public function export(Request $request)
    {
        $event = Event::findOrFail($request->integer('event_id'));

        return $this->csvResponse(
            $this->guests->exportRows($event),
            'vowspace-guests-'.$event->slug.'.csv'
        );
    }

    public function bulk(GuestBulkRequest $request)
    {
        $eventIds = Guest::whereIn('id', $request->input('ids'))->pluck('event_id')->unique();

        if ($eventIds->count() !== 1) {
            return back()->withErrors(['ids' => 'Pilih tamu dari satu event yang sama.']);
        }

        $event = Event::findOrFail($eventIds->first());
        $count = $this->guests->bulkDelete($event, $request->input('ids'), $request->user());

        return back()->with('status', "{$count} tamu dihapus.");
    }

    public function regenerate(Guest $guest)
    {
        $this->guests->regenerateTokens($guest, request()->user());

        return back()->with('status', 'Token undangan dan QR pass berhasil dibuat ulang.');
    }
}
