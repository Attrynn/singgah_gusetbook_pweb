<?php

namespace App\Http\Controllers\Workspace\Concerns;

use App\Models\Event;
use App\Models\Organization;

trait AssertsEventInOrganization
{
    protected function assertEventInOrg(Organization $organization, Event $event): void
    {
        abort_unless((int) $event->organization_id === (int) $organization->id, 404);
    }
}
