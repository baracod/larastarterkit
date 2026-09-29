<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Baracod\Larastarterkit\Core\Helpers\CaseConvert;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConvertRequestToSnakeCase
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $snakeCased = CaseConvert::toSnake($request->except(array_keys($request->allFiles())));

        foreach ($request->allFiles() as $key => $value) {
            $snakeCased[Str::snake($key)] = $value;
        }

        $request->replace($snakeCased);

        return $next($request);
    }
}
