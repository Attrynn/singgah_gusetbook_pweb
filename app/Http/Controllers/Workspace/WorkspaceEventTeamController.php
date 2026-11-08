<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\AssignTeamRequest;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventTeamService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class WorkspaceEventTeamController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EventTeamService $team,
    ) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageTeam', $event);

        return view('workspace.events.team', [
            'organization' => $organization,
            'event' => $event,
            'memberships' => $event->staffMemberships()
                ->with('user')
                ->orderByDesc('status')
                ->orderBy('role')
                ->get(),
            'candidates' => $this->candidateMembers($organization, $event),
            'staffRoles' => EventStaffRole::cases(),
            'primaryPic' => $event->setupConfig()->first()?->primaryPic,
        ]);
    }

    public function store(AssignTeamRequest $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageTeam', $event);

        $data = $request->validated();
        $target = User::findOrFail($data['user_id']);

        $this->team->assign($event, $target, EventStaffRole::from($data['role']), $request->user());

        return back()->with('status', 'Anggota tim ditambahkan.');
    }

    public function update(AssignTeamRequest $request, Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $membership->event_id === (int) $event->id, 404);
        $this->authorize('manageTeam', $event);

        $data = $request->validated();
        $target = User::findOrFail($data['user_id']);
        $role = EventStaffRole::from($data['role']);

        $userChanged = (int) $membership->user_id !== (int) $target->id;
        $roleChanged = $membership->role !== $role;

        if ($userChanged || $roleChanged) {
            // Replace the assignment; EventTeamService keeps a single active
            // row per event+user+role.
            $this->team->remove($event, $membership, $request->user());
            $this->team->assign($event, $target, $role, $request->user());
        }

        return back()->with('status', 'Anggota tim diperbarui.');
    }

    public function destroy(Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        abort_unless((int) $membership->event_id === (int) $event->id, 404);
        $this->authorize('manageTeam', $event);

        $this->team->remove($event, $membership, request()->user());

        return back()->with('status', 'Anggota tim dihapus.');
    }

    public function setPrimary(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageTeam', $event);

        $data = $request->validate([
            'membership_id' => [
                'nullable',
                'integer',
                Rule::exists('event_staff_memberships', 'id')->where('event_id', $event->id),
            ],
        ]);

        $membership = null;

        if (! empty($data['membership_id'])) {
            $membership = EventStaffMembership::query()
                ->whereKey($data['membership_id'])
                ->where('event_id', $event->id)
                ->first();

            if (! $membership) {
                abort(404);
            }
        }

        $this->team->setPrimaryPic($event, $membership, $request->user());

        return back()->with('status', 'Primary PIC diperbarui.');
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

    private function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
