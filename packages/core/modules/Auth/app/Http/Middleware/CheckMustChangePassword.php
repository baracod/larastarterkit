<?php

namespace Modules\Auth\Http\Middleware;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMustChangePassword
{
    /**
     * Block access to all routes except the force-change-password endpoint
     * when the authenticated user has must_change_password = true.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $allowedRoutes = [
            'auth-user-force-change-password',
            'auth-user-security-change-password',
            'auth-login',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes, true)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponse::error(
                'Vous devez changer votre mot de passe avant de continuer.',
                403
            );
        }

        return redirect()->route('auth-force-change-password');
    }
}
