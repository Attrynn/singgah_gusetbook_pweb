<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\EventStaffPermissionGrant;
use App\Models\Organization;
use App\Models\User;

class PermissionService
{
    /**
     * Check if a user has a given permission in the context of an organization.
     */
    public function hasOrgPermission(User $user, Organization $org, string $permission): bool
    {
        // Platform superadmin bypasses all checks.
        if ($user->isPlatformSuperadmin() || $user->isSuperadmin()) {
            return true;
        }

        $membership = $org->membershipFor($user);

        if (! $membership || ! $membership->isActive()) {
            return false;
        }

        $permissions = match ($membership->role) {
            OrganizationRole::Owner => PermissionRegistry::forWorkspaceOwner(),
            OrganizationRole::Admin => PermissionRegistry::forWorkspaceAdmin(),
            default => [PermissionRegistry::ORG_VIEW],
        };

        return in_array($permission, $permissions, true);
    }

    /**
     * Check if a user has a given permission in the context of an event.
     * Checks platform admin bypass, org ownership, and event staff roles.
     * A user may hold multiple staff roles; permissions are unioned across all active roles.
     */
    public function hasEventPermission(User $user, Event $event, string $permission): bool
    {
        // Platform superadmin bypasses all checks.
        if ($user->isPlatformSuperadmin() || $user->isSuperadmin()) {
            return true;
        }

        // Workspace owner/admin inherits full event permissions within their org.
        if ($event->organization_id) {
            $org = $event->organization;
            if ($org && $this->hasOrgPermission($user, $org, $permission)) {
                return true;
            }
        }

        // Check event staff memberships — union permissions across all active roles.
        $staffMemberships = $user->eventStaffMemberships()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->get();

        foreach ($staffMemberships as $membership) {
            $rolePermissions = PermissionRegistry::forEventStaffRole($membership->role);
            if (in_array($permission, $rolePermissions, true)) {
                return true;
            }
        }

        // Explicit grants delegated to an active membership.
        if ($staffMemberships->isNotEmpty()) {
            $granted = EventStaffPermissionGrant::query()
                ->whereIn('event_staff_membership_id', $staffMemberships->modelKeys())
                ->where('permission', $permission)
                ->exists();

            if ($granted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the effective event permissions for a user (role permissions plus grants).
     *
     * @return array<int, string>
     */
    public function effectiveEventPermissions(User $user, Event $event): array
    {
        if ($user->isPlatformSuperadmin() || $user->isSuperadmin()) {
            return PermissionRegistry::forWorkspaceOwner();
        }

        $permissions = [];

        if ($event->organization_id) {
            $org = $event->organization;

            if ($org) {
                $membership = $org->membershipFor($user);

                if ($membership?->isActive()) {
                    $permissions = match ($membership->role) {
                        OrganizationRole::Owner => PermissionRegistry::forWorkspaceOwner(),
                        OrganizationRole::Admin => PermissionRegistry::forWorkspaceAdmin(),
                        default => [PermissionRegistry::ORG_VIEW],
                    };
                }
            }
        }

        $staffMemberships = $user->eventStaffMemberships()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->get();

        foreach ($staffMemberships as $membership) {
            $permissions = array_merge(
                $permissions,
                PermissionRegistry::forEventStaffRole($membership->role),
            );
        }

        if ($staffMemberships->isNotEmpty()) {
            $permissions = array_merge(
                $permissions,
                EventStaffPermissionGrant::query()
                    ->whereIn('event_staff_membership_id', $staffMemberships->modelKeys())
                    ->pluck('permission')
                    ->all(),
            );
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Determine whether an actor may delegate a permission to event staff.
     */
    public function mayDelegateEventPermission(User $actor, Event $event, string $permission): bool
    {
        if (! in_array($permission, PermissionRegistry::delegatableEventPermissions(), true)) {
            return false;
        }

        return $this->hasEventPermission($actor, $event, $permission);
    }

    /**
     * Check if a user is an event owner (couple/partner relationship).
     */
    public function isEventOwner(User $user, Event $event): bool
    {
        if ($user->isPlatformSuperadmin() || $user->isSuperadmin()) {
            return true;
        }

        return $user->eventOwnerMemberships()
            ->where('event_id', $event->id)
            ->where('status', 'active')
            ->exists();
    }
}
