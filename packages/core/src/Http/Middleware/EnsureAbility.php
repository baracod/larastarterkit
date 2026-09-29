<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAbility
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $action, string $subject): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if (! $user->hasRole('administrator') && ! $user->can($action, $subject)) {
            abort(Response::HTTP_FORBIDDEN, 'Vous n\'êtes pas autorisé à effectuer cette action.');
        }

        return $next($request);
    }
}
