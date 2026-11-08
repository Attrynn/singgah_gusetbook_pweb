<?php

namespace App\Services;

use App\Authorization\TenantContext;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;

class TenantContextService
{
    /**
     * Resolve context from the current request.
     *
     * Accepts optional organization/event models (already resolved from route model binding).
     * Validates that the event belongs to the organization if both are present.
     */
    public function resolve(
        User $user,
        ?Organization $organization = null,
        ?Event $event = null,
    ): TenantContext {
        $context = new TenantContext($user, $organization, $event);
        $context->assertEventBelongsToOrganization();

        return $context;
    }

    /**
     * Resolve context for an event, discovering the organization automatically.
     */
    public function forEvent(User $user, Event $event): TenantContext
    {
        $org = $event->organization_id ? $event->organization : null;

        return new TenantContext($user, $org, $event);
    }
}
