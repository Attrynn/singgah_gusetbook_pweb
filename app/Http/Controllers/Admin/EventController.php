<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Services\EventService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventController extends Controller
{
    public function __construct(protected EventService $events) {}

    public function index(Request $request)
    {
        $query = Event::query()->withCount(['guests', 'rsvps', 'checkins']);

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('groom_name', 'like', "%{$search}%")
                ->orWhere('bride_name', 'like', "%{$search}%")
                ->orWhere('venue_name', 'like', "%{$search}%"));
        }

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        $events = $query->orderByDesc('event_date')->paginate(12)->withQueryString();

        return view('admin.events.index', [
            'events' => $events,
            'statuses' => EventStatus::cases(),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return view('admin.events.create', [
            'event' => new Event(['status' => EventStatus::Draft, 'event_date' => now()->addMonth()->toDateString()]),
            'owners' => User::owners()->orderBy('name')->get(),
            'statuses' => EventStatus::cases(),
            'schedules' => [],
        ]);
    }

    public function store(EventRequest $request)
    {
        $event = $this->events->create($request->validated(), $request->user());
        $this->syncOwners($event, $request);

        return redirect()->route('admin.events.show', $event)
            ->with('status', 'Event berhasil dibuat. Undangan dan QR tamu akan dibuat otomatis saat tamu ditambahkan.');
    }

    public function show(Event $event)
    {
        $event->load(['owners', 'primaryVenue', 'schedules', 'giftAccounts'])
            ->loadCount(['guests', 'rsvps', 'checkins', 'messages', 'mediaAssets', 'seatingTables']);

        $stats = app(ReportService::class)->eventStats($event);

        return view('admin.events.show', compact('event', 'stats'));
    }

    public function edit(Event $event)
    {
        $event->load(['schedules', 'owners']);

        return view('admin.events.edit', [
            'event' => $event,
            'owners' => User::owners()->orderBy('name')->get(),
            'statuses' => EventStatus::cases(),
        ]);
    }

    public function update(EventRequest $request, Event $event)
    {
        $this->events->update($event, $request->validated(), $request->user());
        $this->syncOwners($event, $request);

        return redirect()->route('admin.events.show', $event)->with('status', 'Event berhasil diperbarui.');
    }

    public function destroy(Event $event)
    {
        $this->events->delete($event, request()->user());

        return redirect()->route('admin.events.index')->with('status', 'Event dihapus.');
    }

    public function status(Request $request, Event $event)
    {
        $data = $request->validate(['status' => ['required', Rule::enum(EventStatus::class)]]);
        $this->events->setStatus($event, EventStatus::from($data['status']), $request->user());

        return back()->with('status', 'Status event diperbarui menjadi '.$event->fresh()->status->label().'.');
    }

    public function preview(Event $event)
    {
        $guest = new Guest([
            'event_id' => $event->id,
            'name' => 'Tamu Undangan',
            'max_attendees' => 2,
            'rsvp_status' => 'pending',
        ]);
        $guest->setRelation('event', $event);

        return view('guest.invitation', [
            'event' => $event,
            'guest' => $guest,
            'token' => null,
            'preview' => true,
        ]);
    }

    protected function syncOwners(Event $event, Request $request): void
    {
        $submitted = $request->input('owner_ids', []);
        $ids = collect(is_array($submitted) ? $submitted : [$submitted])->filter()->unique()->values();
        $roles = $request->input('owner_roles', []);

        if ($ids->isEmpty()) {
            $event->owners()->sync([]);

            return;
        }

        $validOwnerIds = User::whereIn('id', $ids)
            ->where('role', UserRole::Owner)
            ->pluck('id');

        if ($validOwnerIds->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'owner_ids' => 'Semua pemilik yang dipilih harus memiliki peran Owner yang valid.',
            ]);
        }

        $sync = $validOwnerIds->mapWithKeys(fn ($id) => [
            $id => ['role_label' => $roles[$id] ?? 'partner'],
        ])->all();

        $event->owners()->sync($sync);
    }
}
