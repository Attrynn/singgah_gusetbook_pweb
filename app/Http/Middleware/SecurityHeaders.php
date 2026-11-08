<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for browser-facing responses.
 *
 * The Content-Security-Policy is enforced, not report-only. Inline scripts are
 * permitted only when they carry the per-response nonce, and the @vite bundle
 * carries the same nonce. Two deliberate, documented exceptions remain:
 *
 *  - 'unsafe-eval' is required by Alpine.js, whose expression evaluator uses
 *    Function(). Removing it would disable every Alpine interaction in the
 *    application.
 *  - 'unsafe-inline' for styles covers Tailwind utility style attributes
 *    (e.g. progress bars) and theme variables.
 *
 * Camera access is limited to the application's own origin so the QR check-in
 * scanner keeps working while third-party frames stay blocked.
 */
class SecurityHeaders
{
    /**
     * Paths whose bearer-style tokens must never be cached or leaked by
     * referrers (invitation links, email verification, private media).
     */
    protected array $tokenPaths = ['invite/*', 'email/verify/*', 'media/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);

        $request->attributes->set('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $sensitive = $request->is(...$this->tokenPaths);

        $policy = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "media-src 'self'",
            "worker-src 'self' blob:",
            "connect-src 'self'",
            "manifest-src 'self'",
        ]);

        if ($request->secure()) {
            $policy .= '; upgrade-insecure-requests';
        }

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => $sensitive ? 'no-referrer' : 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => $policy,
        ];

        if ($request->secure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($sensitive) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
