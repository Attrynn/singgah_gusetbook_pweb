<?php

namespace App\Policies;

use App\Authorization\PermissionRegistry;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\PermissionService;

class EventPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user, ?Organization $organization = null): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        if ($organization === null) {
            return $user->organizationMemberships()->where('status', 'active')->exists();
        }

        return $this->permissions->hasOrgPermission($user, $organization, PermissionRegistry::EVENT_VIEW);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->canAccessEvent($event);
    }

    public function create(User $user, ?Organization $organization = null): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        if ($organization === null) {
            return false;
        }

        return $this->permissions->hasOrgPermission($user, $organization, PermissionRegistry::ORG_MANAGE_EVENTS);
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($this->isArchived($event)) {
            return false;
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
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_UPDATE);
    }

    public function manageSetup(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_SETUP_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageTeam(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_TEAM_MANAGE)
            && ! $this->isArchived($event);
    }

    public function manageSchedule(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_SCHEDULE_MANAGE)
            && ! $this->isArchived($event);
    }

    public function viewRunOfShow(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_RUN_OF_SHOW_VIEW);
    }

    public function manageRunOfShow(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_RUN_OF_SHOW_MANAGE)
            && ! $this->isArchived($event);
    }

    public function archive(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_ARCHIVE)
            && ! $this->isArchived($event);
    }

    public function unarchive(User $user, Event $event): bool
    {
        return $this->allowsPermission($user, $event, PermissionRegistry::EVENT_ARCHIVE)
            && $this->isArchived($event);
    }

    /**
     * Platform superadmins bypass every event permission check.
     */
    protected function allowsPermission(User $user, Event $event, string $permission): bool
    {
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return true;
        }

        return $this->permissions->hasEventPermission($user, $event, $permission);
    }

    protected function isArchived(Event $event): bool
    {
        return $event->status === EventStatus::Archived;
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
