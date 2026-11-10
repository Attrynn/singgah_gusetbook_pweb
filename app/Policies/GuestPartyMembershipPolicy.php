<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Models\Event;
use App\Models\GuestPartyMembership;
use App\Models\User;
use App\Services\PermissionService;

class GuestPartyMembershipPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function view(User $user, GuestPartyMembership $member): bool
    {
        return $user->canAccessEvent($member->event_id)
            || $this->permissions->hasEventPermission($user, $member->guest?->event, PermissionRegistry::GUEST_VIEW);
    }

    public function create(User $user, Event $event): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_CREATE);
    }

    public function update(User $user, GuestPartyMembership $member): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $member->guest?->event, PermissionRegistry::GUEST_UPDATE);
    }

    public function delete(User $user, GuestPartyMembership $member): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $member->guest?->event, PermissionRegistry::GUEST_DELETE);
    }

    public function issuePass(User $user, GuestPartyMembership $member): bool
    {
        return $this->update($user, $member);
    }

    public function revokePass(User $user, GuestPartyMembership $member): bool
    {
        return $this->update($user, $member);
    }
}
