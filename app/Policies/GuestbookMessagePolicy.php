<?php

namespace App\Policies;

use App\Models\GuestbookMessage;
use App\Models\User;

class GuestbookMessagePolicy
{
    public function view(User $user, GuestbookMessage $message): bool
    {
        return $user->canAccessEvent($message->event_id);
    }

    public function moderate(User $user, GuestbookMessage $message): bool
    {
        return $user->isSuperadmin();
    }

    public function favorite(User $user, GuestbookMessage $message): bool
    {
        return $user->isOwner() && $user->canAccessEvent($message->event_id);
    }
}
