<?php

namespace App\Providers;

use App\Models\GuestPartyMembership;
use App\Models\GuestPass;
use App\Models\Organization;
use App\Policies\GuestPartyMembershipPolicy;
use App\Policies\GuestPassPolicy;
use App\Policies\OrganizationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(GuestPartyMembership::class, GuestPartyMembershipPolicy::class);
        Gate::policy(GuestPass::class, GuestPassPolicy::class);

        RateLimiter::for('guest-actions', function (Request $request) {
            return Limit::perMinute(config('vowspace.rate_limits.guest_actions_per_minute', 12))
                ->by(($request->route('token') ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('guestbook', function (Request $request) {
            return Limit::perMinute(config('vowspace.rate_limits.guestbook_per_minute', 4))
                ->by(($request->route('token') ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('guest-uploads', function (Request $request) {
            return Limit::perMinute(config('vowspace.rate_limits.uploads_per_minute', 6))
                ->by(($request->route('token') ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('guest-poll', function (Request $request) {
            return Limit::perMinute(config('vowspace.rate_limits.poll_per_minute', 6))
                ->by(($request->route('token') ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('memory-contribute', function (Request $request) {
            return Limit::perMinute(config('memory.rate_limits.contribute', 10))
                ->by(($request->route('token') ?? 'guest').'|'.$request->ip());
        });

        RateLimiter::for('memory-public', function (Request $request) {
            return Limit::perMinute(config('memory.rate_limits.public', 30))
                ->by(($request->route('token') ?? 'memory').'|'.$request->ip());
        });
    }
}
