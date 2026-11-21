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
     * referrers (invitation links, email verification, private media,
     * public shares).
     */
    protected array $tokenPaths = ['invite/*', 'email/verify/*', 'media/*', 'm/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(24);

        $request->attributes->set('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $sensitive = $request->is(...$this->tokenPaths);

        $scriptSrc = ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'"];
        $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $connectSrc = ["'self'"];

        if (app()->environment('local')) {
            $devHosts = [
                'http://localhost:*',
                'http://127.0.0.1:*',
                'http://[::1]:*',
            ];
            $devWsHosts = [
                'ws://localhost:*',
                'ws://127.0.0.1:*',
                'ws://[::1]:*',
            ];

            if (file_exists(public_path('hot'))) {
                $hotUrl = trim((string) @file_get_contents(public_path('hot')));
                if ($hotUrl !== '') {
                    $devHosts[] = $hotUrl;
                    $wsUrl = preg_replace('/^http:/i', 'ws:', $hotUrl);
                    if ($wsUrl) {
                        $devWsHosts[] = $wsUrl;
                    }
                }
            }

            $devHosts = array_values(array_unique($devHosts));
            $devWsHosts = array_values(array_unique($devWsHosts));

            $scriptSrc = array_merge($scriptSrc, $devHosts);
            $styleSrc = array_merge($styleSrc, $devHosts);
            $connectSrc = array_merge($connectSrc, $devHosts, $devWsHosts);
        }

        $policy = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            "img-src 'self' data: blob:",
            "font-src 'self' data: https://fonts.gstatic.com",
            "media-src 'self'",
            "worker-src 'self' blob:",
            'connect-src '.implode(' ', $connectSrc),
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
