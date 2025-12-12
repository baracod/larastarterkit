<?php


namespace Baracod\Larastarterkit\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConvertRequestToSnakeCase
{
    use \Baracod\Larastarterkit\Traits\CaseConvert;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $snakeCased =  $this->toSnake($request->except(array_keys($request->allFiles())));

        foreach ($request->allFiles() as $key => $value) {
            $snakeCased[Str::snake($key)] = $value;
        }

        $request->replace($snakeCased);

        return $next($request);
    }
}
