<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Concerns\ExportsCsv;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\BulkGuestPartyRequest;
use App\Http\Requests\Workspace\StoreGuestPartyRequest;
use App\Http\Requests\Workspace\UpdateGuestPartyRequest;
use App\Models\Event;
use App\Models\Guest;
use App\Models\GuestCategory;
use App\Models\GuestGroup;
use App\Models\GuestPartyMembership;
use App\Models\Organization;
use App\Services\GuestLegacyMigrationService;
use App\Services\GuestPartyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceGuestController extends Controller
{
    use AuthorizesRequests, ExportsCsv;

    public function __construct(
        private readonly GuestPartyService $parties,
        private readonly GuestLegacyMigrationService $migration,
    ) {}

    /**
     * Index invitation parties for the event with filters.
     */
    public function index(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewGuests', $event);

        $stats = $this->parties->getOverviewStats($event);

        $query = Guest::where('event_id', $event->id)
            ->with(['category', 'household'])
            ->withCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')]);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('members', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($categoryId = (int) $request->integer('guest_category_id')) {
            $query->where('guest_category_id', $categoryId);
        }

        if ($groupId = (int) $request->integer('guest_group_id')) {
            $query->where('guest_group_id', $groupId);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $parties = $query->latest('id')->paginate(15)->withQueryString();

        $categories = GuestCategory::where('event_id', $event->id)->orderBy('name')->get();
        $households = GuestGroup::where('event_id', $event->id)->orderBy('name')->get();

        return view('workspace.events.guests.index', [
            'organization' => $organization,
            'event' => $event,
            'stats' => $stats,
            'parties' => $parties,
            'categories' => $categories,
            'households' => $households,
            'filters' => $request->only(['search', 'guest_category_id', 'guest_group_id', 'status']),
        ]);
    }

    /**
     * Create form page.
     */
    public function create(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageGuests', $event);

        $categories = GuestCategory::where('event_id', $event->id)->orderBy('name')->get();
        $households = GuestGroup::where('event_id', $event->id)->orderBy('name')->get();

        return view('workspace.events.guests.create', [
            'organization' => $organization,
            'event' => $event,
            'categories' => $categories,
            'households' => $households,
        ]);
    }

    /**
     * Store a new invitation party.
     */
    public function store(StoreGuestPartyRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageGuests', $event);

        $party = $this->parties->createParty($event, $request->validated(), $request->user());

        return redirect()
            ->route('workspace.events.guests.show', [$organization, $event, $party])
            ->with('status', "Invitation party '{$party->name}' berhasil dibuat.");
    }

    /**
     * Show a party detail.
     */
    public function show(Organization $organization, Event $event, Guest $guest)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        $this->authorize('view', $guest);

        $guest->load([
            'category',
            'household',
            'invitation',
            'rsvp',
            'members' => fn ($query) => $query->with('passes')->orderBy('created_at'),
        ]);

        $otherParties = Guest::where('event_id', $event->id)
            ->where('id', '!=', $guest->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('workspace.events.guests.show', [
            'organization' => $organization,
            'event' => $event,
            'party' => $guest,
            'otherParties' => $otherParties,
        ]);
    }

    /**
     * Edit a party.
     */
    public function edit(Organization $organization, Event $event, Guest $guest)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        $this->authorize('update', $guest);

        $categories = GuestCategory::where('event_id', $event->id)->orderBy('name')->get();
        $households = GuestGroup::where('event_id', $event->id)->orderBy('name')->get();

        return view('workspace.events.guests.edit', [
            'organization' => $organization,
            'event' => $event,
            'party' => $guest,
            'categories' => $categories,
            'households' => $households,
        ]);
    }

    /**
     * Update a party.
     */
    public function update(UpdateGuestPartyRequest $request, Organization $organization, Event $event, Guest $guest)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        $this->authorize('update', $guest);

        $party = $this->parties->updateParty($guest, $request->validated(), $request->user());

        return redirect()
            ->route('workspace.events.guests.show', [$organization, $event, $party])
            ->with('status', "Data invitation party '{$party->name}' berhasil diperbarui.");
    }

    /**
     * Archive (delete) a party.
     */
    public function destroy(Organization $organization, Event $event, Guest $guest)
    {
        $this->assertEventInOrg($organization, $event);
        $this->assertGuestInEvent($event, $guest);
        $this->authorize('delete', $guest);

        $name = $guest->name;
        $this->parties->archiveParty($guest, auth()->user());

        return redirect()
            ->route('workspace.events.guests.index', [$organization, $event])
            ->with('status', "Invitation party '{$name}' berhasil diarsipkan.");
    }

    /**
     * Roster of all identified individuals across parties in the event.
     */
    public function individuals(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewGuests', $event);

        $query = GuestPartyMembership::where('event_id', $event->id)
            ->with(['guest.category', 'guest.household', 'passes']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('guest', fn ($q2) => $q2->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $members = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('workspace.events.guests.individuals', [
            'organization' => $organization,
            'event' => $event,
            'members' => $members,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * Import CSV page.
     */
    public function importForm(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageGuests', $event);

        return view('workspace.events.guests.import', [
            'organization' => $organization,
            'event' => $event,
        ]);
    }

    /**
     * Import parties from a CSV file.
     */
    public function import(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageGuests', $event);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $result = $this->parties->importCsv($event, $request->file('file')->getRealPath(), $request->user());

        return redirect()
            ->route('workspace.events.guests.index', [$organization, $event])
            ->with('status', "Berhasil mengimpor {$result['imported']} invitation party beserta individu anggota.");
    }

    /**
     * Export parties or individuals as CSV.
     */
    public function export(Request $request, Organization $organization, Event $event): StreamedResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewGuests', $event);

        $type = (string) $request->input('type', 'parties');

        if ($type === 'individuals') {
            $rows = $this->individualExportRows($event);

            return $this->csvResponse($rows, 'tamu-individu-'.$event->slug.'.csv');
        }

        $rows = $this->partyExportRows($event);

        return $this->csvResponse($rows, 'invitation-parties-'.$event->slug.'.csv');
    }

    /**
     * Bulk operations (archive / reassign category or household).
     */
    public function bulk(BulkGuestPartyRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageGuests', $event);

        $validated = $request->validated();
        $guestIds = $validated['guest_ids'];
        $action = $validated['action'];

        $validGuests = Guest::where('event_id', $event->id)
            ->whereIn('id', $guestIds)
            ->get();

        if ((int) $validGuests->count() !== count($guestIds)) {
            abort(403, 'Aksi bulk ditolak: beberapa ID tidak valid atau bukan milik event ini.');
        }

        DB::transaction(function () use ($action, $validGuests, $validated): void {
            foreach ($validGuests as $party) {
                if ($action === 'archive') {
                    $this->parties->archiveParty($party, auth()->user());
                } elseif ($action === 'category') {
                    $party->update(['guest_category_id' => $validated['guest_category_id']]);
                } elseif ($action === 'household') {
                    $party->update(['guest_group_id' => $validated['guest_group_id']]);
                }
            }
        });

        return back()->with('status', 'Operasi bulk berhasil diterapkan pada '.$validGuests->count().' invitation party.');
    }

    /**
     * Run the legacy guest migration for this event.
     */
    public function migrateLegacy(Request $request, Organization $organization, Event $event): JsonResponse
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageGuests', $event);

        $dryRun = (bool) $request->boolean('dry_run');
        $result = $this->migration->migrate($event, $dryRun, $request->user());

        return response()->json([
            'dry_run' => $result['dry_run'],
            'summary' => [
                'total_legacy_guests' => $result['total_legacy_guests'],
                'parties_mapped' => $result['total_parties_mapped'],
                'members_created' => $result['total_members_created'],
                'passes_issued' => $result['total_passes_issued'],
                'skipped' => $result['already_migrated_skipped'],
                'rsvps_preserved' => $result['total_rsvps_preserved'],
                'checkins_preserved' => $result['total_checkins_preserved'],
                'tokens_preserved' => $result['total_tokens_preserved'],
                'unidentified_capacity_retained' => $result['unidentified_capacity_retained'],
            ],
            'errors' => $result['errors'],
        ]);
    }

    protected function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }

    protected function assertGuestInEvent(Event $event, Guest $guest): void
    {
        abort_unless((int) $guest->event_id === (int) $event->id, 404);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function partyExportRows(Event $event): array
    {
        $rows = [[
            'Nama Party', 'Kuota', 'Individu Terdaftar', 'RSVP Hadir', 'Kategori', 'Household', 'Status',
        ]];

        Guest::where('event_id', $event->id)
            ->with(['category', 'household', 'rsvp'])
            ->withCount(['members as active_members_count' => fn ($query) => $query->where('status', 'active')])
            ->orderBy('name')
            ->chunk(200, function ($parties) use (&$rows): void {
                foreach ($parties as $party) {
                    $rows[] = [
                        $party->name,
                        $party->max_attendees,
                        $party->active_members_count,
                        $party->rsvp?->attendance_count ?? 0,
                        $party->category?->name ?? '-',
                        $party->household?->name ?? '-',
                        $party->status->label(),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function individualExportRows(Event $event): array
    {
        $rows = [[
            'Nama Individu', 'Invitation Party', 'Kategori', 'Household', 'Status Individu', 'Status Pass',
        ]];

        GuestPartyMembership::where('event_id', $event->id)
            ->with(['guest.category', 'guest.household', 'passes'])
            ->orderBy('name')
            ->chunk(200, function ($members) use (&$rows): void {
                foreach ($members as $member) {
                    $activePass = $member->passes->firstWhere('status', 'active');

                    $rows[] = [
                        $member->name,
                        $member->guest?->name,
                        $member->guest?->category?->name ?? '-',
                        $member->guest?->household?->name ?? '-',
                        $member->status->label(),
                        $activePass ? 'Tersedia' : 'Belum Diterbitkan',
                    ];
                }
            });

        return $rows;
    }
}
