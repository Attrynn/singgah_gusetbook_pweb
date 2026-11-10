<?php

namespace App\Http\Controllers\Workspace;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStaffRole;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Workspace\Concerns\AssertsEventInOrganization;
use App\Models\Event;
use App\Models\EventStaffMembership;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\StaffOperationsService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceOperationsStaffController extends Controller
{
    use AssertsEventInOrganization, AuthorizesRequests;

    public function __construct(
        private readonly StaffOperationsService $staff,
        private readonly PermissionService $permissions,
    ) {}

    public function index(Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('viewStaff', $event);

        $memberships = $event->staffMemberships()
            ->with(['user', 'invitedBy', 'stationAssignments.station', 'permissionGrants'])
            ->orderBy('role')
            ->get();

        $candidates = $organization->memberships()
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->with('user')
            ->get()
            ->filter(fn ($membership) => $membership->user !== null)
            ->values();

        $actor = request()->user();

        return view('workspace.events.operations.staff', [
            'organization' => $organization,
            'event' => $event,
            'memberships' => $memberships,
            'candidates' => $candidates,
            'roles' => EventStaffRole::operationalProfiles(),
            'statuses' => MembershipStatus::cases(),
            'delegatablePermissions' => array_values(array_filter(
                PermissionRegistry::delegatableEventPermissions(),
                fn (string $permission) => $this->permissions->mayDelegateEventPermission($actor, $event, $permission),
            )),
        ]);
    }

    public function invite(Request $request, Organization $organization, Event $event)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('inviteStaff', $event);

        $data = $request->validate([
            'user_id' => [
                'required',
                'integer',
                Rule::exists('organization_memberships', 'user_id')
                    ->where('organization_id', $organization->id)
                    ->where('status', OrganizationMembershipStatus::Active->value),
            ],
            'role' => ['required', Rule::enum(EventStaffRole::class)],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $target = User::query()->findOrFail($data['user_id']);

        $this->staff->invite($event, $target, EventStaffRole::from($data['role']), $request->user(), [
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('status', 'Undangan staf operasional dikirim.');
    }

    public function accept(Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);

        $this->staff->accept($event, $membership, request()->user());

        return back()->with('status', 'Undangan staf diterima.');
    }

    public function decline(Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);

        $this->staff->decline($event, $membership, request()->user());

        return back()->with('status', 'Undangan staf ditolak.');
    }

    public function suspend(Request $request, Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageStaff', $event);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->staff->suspend($event, $membership, $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Akses staf ditangguhkan.');
    }

    public function reinstate(Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageStaff', $event);

        $this->staff->reinstate($event, $membership, request()->user());

        return back()->with('status', 'Akses staf dipulihkan.');
    }

    public function remove(Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageStaff', $event);

        $this->staff->remove($event, $membership, request()->user());

        return back()->with('status', 'Staf dikeluarkan dari tim operasional.');
    }

    public function permissions(Request $request, Organization $organization, Event $event, EventStaffMembership $membership)
    {
        $this->assertEventInOrg($organization, $event);
        $this->authorize('manageStaffPermissions', $event);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::delegatableEventPermissions())],
        ]);

        $this->staff->grantPermissions(
            $event,
            $membership,
            $data['permissions'] ?? [],
            $request->user(),
        );

        return back()->with('status', 'Delegasi izin staf diperbarui.');
    }
}
