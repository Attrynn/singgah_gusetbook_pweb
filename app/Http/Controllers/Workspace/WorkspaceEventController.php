<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\EventOwnerRole;
use App\Enums\EventStaffRole;
use App\Enums\EventStatus;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Enums\RunOfShowStatus;
use App\Enums\SetupStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\SaveEventSetupRequest;
use App\Http\Requests\Workspace\StoreWorkspaceEventRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceEventRequest;
use App\Models\Event;
use App\Models\Organization;
use App\Models\PlaybookVersion;
use App\Models\User;
use App\Services\EventBlueprintService;
use App\Services\EventLifecycleService;
use App\Services\EventService;
use App\Services\EventSetupService;
use App\Services\PlaybookService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class WorkspaceEventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EventService $events,
        private readonly EventLifecycleService $lifecycle,
        private readonly EventSetupService $setup,
        private readonly PlaybookService $playbooks,
        private readonly EventBlueprintService $blueprints,
    ) {}

    public function index(Request $request, Organization $organization)
    {
        $this->authorize('viewAny', [Event::class, $organization]);

        $query = $organization->events()
            ->with(['organization', 'primaryVenue', 'setupConfig.primaryPic.user']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('groom_name', 'like', "%{$search}%")
                    ->orWhere('bride_name', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%");
            });
        }

        $status = (string) $request->input('status');
        if (in_array($status, array_column(EventStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        if ($from = $request->input('date_from')) {
            $query->whereDate('event_date', '>=', $from);
        }

        if ($to = $request->input('date_to')) {
            $query->whereDate('event_date', '<=', $to);
        }

        $sort = (string) $request->input('sort', 'event_date');
        if (! in_array($sort, ['event_date', 'name', 'status', 'created_at'], true)) {
            $sort = 'event_date';
        }

        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        return view('workspace.events.index', [
            'organization' => $organization,
            'events' => $query->paginate(12)->withQueryString(),
            'summary' => $this->summary($organization),
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to', 'sort', 'direction']),
            'statuses' => EventStatus::cases(),
        ]);
    }

    public function create(Request $request, Organization $organization)
    {
        $this->authorize('create', [Event::class, $organization]);

        return view('workspace.events.create', [
            'organization' => $organization,
            'playbooks' => $this->availablePlaybooks($organization),
            'events' => $organization->events()->orderByDesc('event_date')->get(),
            'staffRoles' => EventStaffRole::cases(),
        ]);
    }

    public function store(StoreWorkspaceEventRequest $request, Organization $organization)
    {
        $data = $request->validated();

        // Never trust an organization from the request body.
        $data['organization_id'] = $organization->id;
        $data['groom_name'] = $data['groom_name'] ?? null;
        $data['bride_name'] = $data['bride_name'] ?? null;

        if (! empty($data['duplicate_source_event_id'])) {
            $event = $this->duplicateFromSource($organization, $data, $request->user());
        } else {
            $version = null;
            if (! empty($data['playbook_version_id'])) {
                $version = $this->resolvePlaybookVersion($organization, (int) $data['playbook_version_id']);

                if (! $version) {
                    abort(404);
                }
            }

            $event = $this->events->create($data, $request->user());

            if ($version) {
                $this->setup->applyPlaybook($event, $version, $request->user());
            }
        }

        return redirect()->route('workspace.events.setup', [$organization, $event])
            ->with('status', 'Event berhasil dibuat. Lanjutkan konfigurasi setup.');
    }

    /**
     * Create a new event from an existing blueprint, then apply the identity
     * fields supplied in the creation form. Only same-organization sources.
     *
     * @param  array<string, mixed>  $data
     */
    private function duplicateFromSource(Organization $organization, array $data, User $actor): Event
    {
        $source = $organization->events()
            ->whereKey((int) $data['duplicate_source_event_id'])
            ->first();

        if (! $source) {
            abort(404);
        }

        $event = $this->blueprints->duplicate($source, [
            'name' => $data['name'],
            'event_date' => $data['event_date'] ?? null,
        ], $actor);

        $event->update([
            'groom_name' => $data['groom_name'] ?? null,
            'bride_name' => $data['bride_name'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'venue_name' => $data['venue_name'] ?? null,
            'venue_address' => $data['venue_address'] ?? null,
            'google_maps_url' => $data['google_maps_url'] ?? null,
            'timezone' => $data['timezone'] ?? $source->timezone,
        ]);

        return $event->fresh();
    }

    public function show(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('view', $event);

        $event->load(['organization', 'primaryVenue', 'setupConfig.primaryPic.user', 'staffMemberships.user']);

        return view('workspace.events.show', [
            'organization' => $organization,
            'event' => $event,
            'setup' => $event->setupConfig,
            'readiness' => $this->setup->evaluate($event),
            'teamMembers' => $event->staffMemberships
                ->where('status', MembershipStatus::Active)
                ->values(),
            'scheduleCount' => $event->schedules()->count(),
            'runOfShowSummary' => $this->runOfShowSummary($event),
        ]);
    }

    public function edit(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('update', $event);

        return view('workspace.events.edit', [
            'organization' => $organization,
            'event' => $event->load(['primaryVenue', 'owners']),
            'playbooks' => $this->availablePlaybooks($organization),
            'owners' => $event->owners()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateWorkspaceEventRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('update', $event);

        $data = $request->validated();
        unset($data['status'], $data['organization_id']);
        $data['groom_name'] = $data['groom_name'] ?? null;
        $data['bride_name'] = $data['bride_name'] ?? null;

        $this->events->update($event, $data, $request->user());

        return redirect()->route('workspace.events.show', [$organization, $event])
            ->with('status', 'Event berhasil diperbarui.');
    }

    public function status(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);

        $data = $request->validate([
            'status' => ['required', Rule::enum(EventStatus::class)],
        ]);

        $target = EventStatus::from($data['status']);

        if ($target === EventStatus::Archived) {
            $this->authorize('archive', $event);
        } elseif ($target === EventStatus::Published && $event->status === EventStatus::Archived) {
            $this->authorize('unarchive', $event);
        } elseif ($target === EventStatus::Published) {
            $this->authorize('publish', $event);
        } else {
            $this->authorize('update', $event);
        }

        $this->lifecycle->transition($event, $target, $request->user());

        return back()->with('status', 'Status event diperbarui menjadi '.$target->label().'.');
    }

    public function archive(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('archive', $event);

        $this->lifecycle->transition($event, EventStatus::Archived, request()->user());

        return back()->with('status', 'Event diarsipkan.');
    }

    public function unarchive(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('unarchive', $event);

        $this->lifecycle->transition($event, EventStatus::Published, request()->user());

        return back()->with('status', 'Event diaktifkan kembali.');
    }

    public function setup(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageSetup', $event);

        return view('workspace.events.setup', [
            'organization' => $organization,
            'event' => $event,
            'step' => (int) request()->query('step', 1),
            'playbooks' => $this->availablePlaybooks($organization),
            'setup' => $event->setupConfig()->first(),
            'phases' => $event->phases()->get(),
            'sessions' => $event->sessions()->get(),
            'venues' => $event->venues()->get(),
            'teamMemberships' => $event->staffMemberships()->with('user')->get(),
            'candidateMembers' => $this->candidateMembers($organization, $event),
            'staffRoles' => EventStaffRole::cases(),
            'coupleRoles' => EventOwnerRole::cases(),
            'events' => $organization->events()->orderByDesc('event_date')->get(),
        ]);
    }

    public function saveSetup(SaveEventSetupRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageSetup', $event);

        $data = $request->validated();

        if (! empty($data['playbook_version_id'])) {
            $version = $this->resolvePlaybookVersion($organization, (int) $data['playbook_version_id']);

            if ($version) {
                $this->setup->applyPlaybook($event, $version, $request->user());
            }
        }

        $this->setup->updateConfig($event, $data, $request->user());

        foreach ($data['checklist'] ?? [] as $item) {
            $sourceKey = $item['source_key'] ?? null;

            if ($sourceKey !== null && $sourceKey !== '') {
                $this->setup->completeChecklistItem(
                    $event,
                    (string) $sourceKey,
                    (bool) ($item['completed'] ?? false),
                    $request->user(),
                );
            }
        }

        if (array_key_exists('sessions', $data)) {
            $this->setup->syncSessions(
                $event,
                is_array($data['sessions']) ? $data['sessions'] : [],
                $request->user(),
            );
        }

        return back()->with('status', 'Konfigurasi setup disimpan.');
    }

    public function finalizeSetup(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageSetup', $event);

        $setup = $this->setup->finalize($event, $request->user());

        return redirect()->route('workspace.events.show', [$organization, $event])
            ->with('status', $setup->setup_status === SetupStatus::Ready
                ? 'Setup event siap.'
                : 'Setup event belum lengkap.');
    }

    /**
     * @return array<string, int>
     */
    private function summary(Organization $organization): array
    {
        $base = $organization->events();

        return [
            'total' => (clone $base)->count(),
            'draft' => (clone $base)->where('status', EventStatus::Draft->value)->count(),
            'upcoming' => (clone $base)->where('status', EventStatus::Published->value)->count(),
            'in_progress' => (clone $base)->where('status', EventStatus::Live->value)->count(),
            'completed' => (clone $base)->where('status', EventStatus::Completed->value)->count(),
            'archived' => (clone $base)->where('status', EventStatus::Archived->value)->count(),
            'setup_incomplete' => (clone $base)->whereDoesntHave('setupConfig', function ($query): void {
                $query->where('setup_status', SetupStatus::Ready->value);
            })->count(),
            'no_manager' => (clone $base)->whereDoesntHave('setupConfig', function ($query): void {
                $query->whereNotNull('primary_pic_staff_membership_id');
            })->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function runOfShowSummary(Event $event): array
    {
        $items = $event->runOfShowItems();

        return [
            'total' => (clone $items)->count(),
            'pending' => (clone $items)->where('status', RunOfShowStatus::Pending->value)->count(),
            'in_progress' => (clone $items)->where('status', RunOfShowStatus::InProgress->value)->count(),
            'completed' => (clone $items)->where('status', RunOfShowStatus::Completed->value)->count(),
        ];
    }

    private function availablePlaybooks(Organization $organization): Collection
    {
        return $this->playbooks->platformTemplates()
            ->concat($this->playbooks->forOrganization($organization));
    }

    private function candidateMembers(Organization $organization, Event $event): Collection
    {
        $assignedUserIds = $event->staffMemberships()
            ->where('status', MembershipStatus::Active->value)
            ->pluck('user_id')
            ->all();

        return $organization->memberships()
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->whereNotIn('user_id', $assignedUserIds)
            ->with('user')
            ->get();
    }

    private function resolvePlaybookVersion(Organization $organization, int $id): ?PlaybookVersion
    {
        return PlaybookVersion::query()
            ->whereKey($id)
            ->whereHas('playbook', function ($query) use ($organization): void {
                $query->where(function ($builder) use ($organization): void {
                    $builder->whereNull('organization_id')
                        ->orWhere('organization_id', $organization->id);
                });
            })
            ->first();
    }

    private function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
