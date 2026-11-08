<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Models\Event;
use App\Models\User;
use App\Services\PermissionService;

class EventPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $user->isPlatformSuperadmin();
    }

    public function view(User $user, Event $event): bool
    {
        return $user->canAccessEvent($event);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::EVENT_UPDATE);
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::EVENT_DELETE);
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->isSuperadmin();
    }

    public function manageGuests(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_CREATE);
    }

    public function viewGuests(User $user, Event $event): bool
    {
        if ($user->canAccessEvent($event)) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::GUEST_VIEW);
    }

    public function moderate(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::MEDIA_MODERATE);
    }

    public function manageSettings(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, PermissionRegistry::EVENT_MANAGE_SETTINGS);
    }
}
