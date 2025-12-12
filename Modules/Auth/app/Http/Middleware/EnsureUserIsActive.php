<?php

namespace Modules\Auth\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Ici, on suppose que le middleware 'auth' a déjà tourné
        $user = $request->user();

        if (!$user) {
            // Laisse le middleware 'auth' gérer l'Unauthenticated
            return $next($request);
        }

        // 3 variantes courantes — choisis celle qui correspond à ton schéma
        $isSuspended = !$user->active;        // timestamp nullable


        if ($isSuspended) {
            // Révocation optionnelle du token Sanctum (utile côté API)
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            // Réponse orientée API vs Web
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account suspended.',
                    'code'    => 'ACCOUNT_SUSPENDED',
                ], 423); // 423 Locked
            }

            // Côté web : redirige avec message
            return redirect()->route('login')->withErrors([
                'email' => 'Votre compte est suspendu. Contactez le support.',
            ]);
        }

        return $next($request);
    }
}
