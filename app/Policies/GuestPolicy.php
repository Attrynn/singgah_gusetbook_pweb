<?php

namespace App\Policies;

use App\Models\Guest;
use App\Models\User;

class GuestPolicy
{
    public function view(User $user, Guest $guest): bool
    {
        return $user->canAccessEvent($guest->event_id);
    }

    public function update(User $user, Guest $guest): bool
    {
        return $user->isSuperadmin();
    }

    public function delete(User $user, Guest $guest): bool
    {
        return $user->isSuperadmin();
    }

    public function manage(User $user, Guest $guest): bool
    {
        return $user->isSuperadmin();
    }
}
