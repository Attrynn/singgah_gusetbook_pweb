<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Superadmin bypasses org membership requirement.
        if ($user->isSuperadmin() || $user->isPlatformSuperadmin()) {
            return $next($request);
        }

        $org = $request->route('organization');

        if (! $org instanceof Organization) {
            abort(403, 'Organization context required.');
        }

        $membership = $org->membershipFor($user);

        if (! $membership || ! $membership->isActive()) {
            abort(403, 'Anda tidak memiliki akses ke workspace ini.');
        }

        return $next($request);
    }
}
