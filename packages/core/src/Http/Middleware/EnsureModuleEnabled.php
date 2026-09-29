<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(app(ModuleRegistry::class)->enabled($module), 404);

        return $next($request);
    }
}
