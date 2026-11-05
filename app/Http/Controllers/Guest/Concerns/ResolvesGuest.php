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
}
