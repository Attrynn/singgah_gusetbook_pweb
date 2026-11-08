<?php

namespace App\Services;

use App\Authorization\PermissionRegistry;
use App\Enums\OrganizationRole;
use App\Models\Event;
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

        return false;
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
