<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Models\GuestPass;
use App\Models\User;
use App\Services\PermissionService;

class GuestPassPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function view(User $user, GuestPass $pass): bool
    {
        return $user->canAccessEvent($pass->event_id)
            || $this->permissions->hasEventPermission($user, $pass->event, PermissionRegistry::GUEST_VIEW);
    }

    public function revoke(User $user, GuestPass $pass): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $pass->event, PermissionRegistry::GUEST_UPDATE);
    }
}
