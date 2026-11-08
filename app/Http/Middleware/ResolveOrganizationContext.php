<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = $request->route('organization');

        if ($org instanceof Organization) {
            view()->share('currentOrganization', $org);
        }

        return $next($request);
    }
}
