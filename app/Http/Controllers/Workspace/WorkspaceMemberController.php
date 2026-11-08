<?php

namespace App\Http\Controllers\Workspace;

use App\Authorization\PermissionRegistry;
use App\Enums\EventOwnerRole;
use App\Enums\EventStaffRole;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Services\PermissionService;
use App\Services\WorkspaceInvitationService;
use App\Services\WorkspaceMembershipService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceMemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly WorkspaceInvitationService $invitationService,
        private readonly WorkspaceMembershipService $membershipService,
        private readonly PermissionService $permissionService,
    ) {}

    public function index(Organization $organization)
    {
        $this->authorize('manageMembers', $organization);

        $user = auth()->user();

        $members = $organization->memberships()
            ->with('user', 'invitedBy')
            ->orderByDesc('role')
            ->get();

        $pendingInvitations = WorkspaceInvitation::where('organization_id', $organization->id)
            ->where('status', 'pending')
            ->with('invitedBy')
            ->latest()
            ->get();

        $events = $organization->events()->orderBy('event_date')->get();

        $actingMembership = $organization->membershipFor($user);
        $actingRole = $actingMembership?->role;

        $isPlatformActor = $user->isSuperadmin() || $user->isPlatformSuperadmin();

        $canManageRoles = $isPlatformActor
            || $this->permissionService->hasOrgPermission($user, $organization, PermissionRegistry::ORG_MANAGE_ROLES);

        $canInvite = $isPlatformActor
            || $this->permissionService->hasOrgPermission($user, $organization, PermissionRegistry::ORG_INVITE_MEMBERS);

        return view('workspace.members', [
            'organization' => $organization,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'events' => $events,
            'actingMembership' => $actingMembership,
            'actingRole' => $actingRole,
            'canManageRoles' => $canManageRoles,
            'canInvite' => $canInvite,
            'staffRoles' => EventStaffRole::cases(),
            'organizationRoles' => OrganizationRole::cases(),
            'coupleRoles' => EventOwnerRole::cases(),
        ]);
    }

    public function switch(Organization $organization)
    {
        session(['active_organization_id' => $organization->id]);

        $user = auth()->user();

        $canManageMembers = $user->isSuperadmin()
            || $user->isPlatformSuperadmin()
            || $this->permissionService->hasOrgPermission($user, $organization, PermissionRegistry::ORG_MANAGE_MEMBERS);

        $redirect = $canManageMembers
            ? redirect()->route('workspace.members.index', $organization)
            : redirect()->route('home');

        return $redirect->with('status', 'Konteks workspace diganti.');
    }

    public function storeStaffInvitation(Request $request, Organization $organization)
    {
        $this->authorize('inviteMembers', $organization);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'organization_role' => ['required', Rule::in(array_column(OrganizationRole::cases(), 'value'))],
            'assignments' => ['nullable', 'array'],
            'assignments.*.event_id' => ['required_with:assignments', 'integer'],
            'assignments.*.role' => ['required_with:assignments', Rule::in(array_column(EventStaffRole::cases(), 'value'))],
        ]);

        if ($data['organization_role'] === OrganizationRole::Owner->value && ! $this->canGrantOwner($request, $organization)) {
            abort(403, 'Anda tidak dapat memberikan role di atas kewenangan Anda.');
        }

        $assignments = collect($data['assignments'] ?? [])
            ->map(fn (array $assignment): array => [
                'event_id' => (int) $assignment['event_id'],
                'role' => $assignment['role'],
            ])
            ->all();

        $this->invitationService->createStaffInvitation(
            $organization,
            $request->user(),
            $data['email'],
            OrganizationRole::from($data['organization_role']),
            $assignments,
        );

        return redirect()->route('workspace.members.index', $organization)
            ->with('status', 'Undangan staff dikirim.');
    }

    public function storeCoupleInvitation(Request $request, Organization $organization)
    {
        $this->authorize('inviteMembers', $organization);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'event_id' => ['required', 'integer'],
            'role_label' => ['required', Rule::in(array_column(EventOwnerRole::cases(), 'value'))],
        ]);

        $eventExists = Event::where('id', $data['event_id'])
            ->where('organization_id', $organization->id)
            ->exists();

        if (! $eventExists) {
            abort(404);
        }

        $this->invitationService->createCoupleInvitation(
            $organization,
            $request->user(),
            $data['email'],
            (int) $data['event_id'],
            EventOwnerRole::from($data['role_label']),
        );

        return redirect()->route('workspace.members.index', $organization)
            ->with('status', 'Undangan pasangan dikirim.');
    }

    public function revokeInvitation(Request $request, Organization $organization, WorkspaceInvitation $invitation)
    {
        $this->authorize('inviteMembers', $organization);

        if ($invitation->organization_id !== $organization->id) {
            abort(404);
        }

        $this->invitationService->revokeInvitation($invitation, $request->user());

        return back()->with('status', 'Undangan dibatalkan.');
    }

    public function resendInvitation(Request $request, Organization $organization, WorkspaceInvitation $invitation)
    {
        $this->authorize('inviteMembers', $organization);

        if ($invitation->organization_id !== $organization->id) {
            abort(404);
        }

        $this->invitationService->resendInvitation($invitation, $request->user());

        return back()->with('status', 'Undangan dikirim ulang.');
    }

    public function updateMember(Request $request, Organization $organization, User $user)
    {
        $this->authorize('manageRoles', $organization);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_column(OrganizationRole::cases(), 'value'))],
        ]);

        if ($data['role'] === OrganizationRole::Owner->value && ! $this->canGrantOwner($request, $organization)) {
            abort(403, 'Anda tidak dapat memberikan role di atas kewenangan Anda.');
        }

        $this->membershipService->changeRole(
            $organization,
            $user,
            OrganizationRole::from($data['role']),
            $request->user(),
        );

        return back()->with('status', 'Role anggota diperbarui.');
    }

    public function revokeMember(Request $request, Organization $organization, User $user)
    {
        $this->authorize('manageMembers', $organization);

        $this->membershipService->revoke($organization, $user, $request->user());

        return back()->with('status', 'Akses anggota dicabut.');
    }

    /**
     * Only an active Workspace Owner or a platform superadmin may grant the Owner role.
     */
    private function canGrantOwner(Request $request, Organization $organization): bool
    {
        $user = $request->user();

        return $user->isSuperadmin()
            || $user->isPlatformSuperadmin()
            || $user->isWorkspaceOwner($organization);
    }
}
