<?php

use Baracod\Larastarterkit\Core\Http\Middleware\AddSecurityHeaders;
use Baracod\Larastarterkit\Core\Http\Middleware\EnsureAbility;
use Baracod\Larastarterkit\Core\Http\Middleware\EnsureAdministrator;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Auth\Http\Middleware\CheckMustChangePassword;
use Modules\Auth\Http\Middleware\EnsureUserIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->append(AddSecurityHeaders::class);
        $middleware->alias([
            'auth' => Authenticate::class,
            'active' => EnsureUserIsActive::class,
            'ability' => EnsureAbility::class,
            'administrator' => EnsureAdministrator::class,
            'must_change_pass' => CheckMustChangePassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
