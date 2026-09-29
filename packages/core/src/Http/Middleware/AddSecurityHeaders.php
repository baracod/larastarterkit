<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction()) {
            Vite::useCspNonce();
        }

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (app()->isProduction()) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; "
                ."script-src 'self' 'nonce-".Vite::cspNonce()."'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org; "
                ."font-src 'self' data: https://fonts.gstatic.com; frame-src 'self' blob:; worker-src 'self' blob:; connect-src 'self' https: wss:; object-src 'none'"
            );
        }

        return $response;
    }
}
