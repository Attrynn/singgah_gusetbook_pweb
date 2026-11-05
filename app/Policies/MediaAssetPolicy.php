<?php

namespace App\Policies;

use App\Models\MediaAsset;
use App\Models\User;

class MediaAssetPolicy
{
    public function view(User $user, MediaAsset $asset): bool
    {
        return $user->canAccessEvent($asset->event_id);
    }

    public function moderate(User $user, MediaAsset $asset): bool
    {
        return $user->isSuperadmin();
    }
}
