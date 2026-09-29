<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdministrator
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole('administrator')) {
            abort(Response::HTTP_FORBIDDEN, 'Accès réservé aux administrateurs.');
        }

        return $next($request);
    }
}
