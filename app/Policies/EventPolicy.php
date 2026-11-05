<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
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
        return $user->isSuperadmin();
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->isSuperadmin();
    }

    public function publish(User $user, Event $event): bool
    {
        return $user->isSuperadmin();
    }

    public function manageGuests(User $user, Event $event): bool
    {
        return $user->isSuperadmin();
    }

    public function viewGuests(User $user, Event $event): bool
    {
        return $user->canAccessEvent($event);
    }

    public function moderate(User $user, Event $event): bool
    {
        return $user->isSuperadmin();
    }

    public function manageSettings(User $user, Event $event): bool
    {
        return $user->isSuperadmin();
    }
}
