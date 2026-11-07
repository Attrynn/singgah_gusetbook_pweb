<?php

namespace App\Http\Controllers\Guest\Concerns;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Http\Request;

trait ResolvesGuest
{
    protected function guest(Request $request): Guest
    {
        return $request->attributes->get('guest');
    }

    protected function event(Request $request): Event
    {
        return $request->attributes->get('event');
    }

    /**
     * @return array<string, bool>
     */
    protected function capabilities(Request $request): array
    {
        return $request->attributes->get('capabilities', []);
    }

    protected function can(Request $request, string $capability): bool
    {
        return (bool) ($this->capabilities($request)[$capability] ?? false);
    }
}
