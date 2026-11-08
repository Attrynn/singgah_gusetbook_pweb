<?php

namespace App\Http\Middleware;

use App\Services\MfaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MfaChallenge
{
    /** @var array */
    protected $recoveryCodes = [];

    public function handle(Request $request, Closure $next): mixed
    {
        // Only apply to superadmin routes when MFA is required
        if (! $this->isMfaRequired($request)) {
            return $next($request);
        }

        // If the user is already MFA-verified in this session, allow
        if ($request->session()->has('mfa_verified')) {
            return $next($request);
        }

        // If already within MFA lockout window, block
        if ($this->isLockedOut($request)) {
            return $this->mfaFailureResponse($request, 'MFA lockout. Try again later.');
        }

        // Check if this is an MFA verification POST
        if ($request->method() === 'POST' && $request->has('mfa_code')) {
            return $this->verifyMfaCode($request, $next);
        }

        // Present MFA challenge (form or QR enrollment)
        if ($request->routeIs('admin.dashboard') || $request->routeIs('owner.dashboard')) {
            return $next($request); // allow first pass, MFA enforced later
        }

        // For other sensitive routes, challenge MFA
        if ($request->expectsJson()) {
            return response()->json([
                'requires_mfa' => true,
                'mfa_method' => 'totp',
            ], 403);
        }

        // Default: redirect to MFA challenge page
        return redirect()->route('admin.mfa.challenge')
            ->with('mfa_challenge', true);
    }

    protected function isMfaRequired(Request $request): bool
    {
        // Check env: enforce in production only by default
        $enforce = env('MFA_REQUIRE_SUPERADMIN', APP_ENV === 'production');

        if (! $enforce) {
            return false;
        }

        // Only superadmin
        return $request->user()?->role === 'superadmin';
    }

    protected function isLockedOut(Request $request): bool
    {
        $key = 'mfa_lockout:'.$request->user()->id;

        return (bool) Cache::get($key, false);
    }

    protected function markLockedOut(Request $request): void
    {
        $key = 'mfa_lockout:'.$request->user()->id;
        Cache::put($key, true, now()->addMinutes(30));
    }

    protected function verifyMfaCode(Request $request, Closure $next): mixed
    {
        $code = $request->input('mfa_code');

        /** @var MfaService $mfa */
        $mfa = app(MfaService::class);

        if ($mfa->verifyCode(
            $request->user()->mfa_secret ?? '',
            $code
        )) {
            $request->session()->put('mfa_verified', true);

            // Clear any previous lockout
            $key = 'mfa_lockout:'.$request->user()->id;
            Cache::forget($key);

            return $next($request);
        }

        // Record failed attempt for lockout logic
        $this->recordFailure($request, $code);

        return back()->withErrors([
            'mfa_code' => 'Kode TOTP salah. Coba lagi.',
        ]);
    }

    protected function recordFailure(Request $request, string $code): void
    {
        // Simple rate limiting: after 5 failures in 2 minutes, lock out
        $key = 'mfa_failure:'.$request->user()->id;
        $failures = Cache::increment($key);
        if ($failures >= 5) {
            $this->markLockedOut($request);
        }
    }

    protected function mfaFailureResponse(Request $request, string $message): mixed
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 429);
        }

        return back()->withErrors(['general' => $message]);
    }
}
