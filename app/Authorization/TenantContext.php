<?php

namespace App\Authorization;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

final class TenantContext
{
    public function __construct(
        public readonly User $user,
        public readonly ?Organization $organization = null,
        public readonly ?Event $event = null,
    ) {}

    public function hasOrganization(): bool
    {
        return $this->organization !== null;
    }

    public function hasEvent(): bool
    {
        return $this->event !== null;
    }

    /**
     * Assert that the event belongs to the resolved organization.
     * Calls abort(404) if they don't match.
     */
    public function assertEventBelongsToOrganization(): void
    {
        if ($this->event && $this->organization) {
            if ((int) $this->event->organization_id !== (int) $this->organization->id) {
                abort(404);
            }
        }
    }
}
