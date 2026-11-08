<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Models\Guest;
use App\Models\User;
use App\Services\PermissionService;

class GuestPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function view(User $user, Guest $guest): bool
    {
        if ($user->canAccessEvent($guest->event_id)) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $guest->event, PermissionRegistry::GUEST_VIEW);
    }

    public function update(User $user, Guest $guest): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $guest->event, PermissionRegistry::GUEST_UPDATE);
    }

    public function delete(User $user, Guest $guest): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $guest->event, PermissionRegistry::GUEST_DELETE);
    }

    public function manage(User $user, Guest $guest): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $guest->event, PermissionRegistry::GUEST_CREATE);
    }
}
