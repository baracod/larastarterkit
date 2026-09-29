<?php

namespace Modules\Documentation\Providers;

use Illuminate\Support\ServiceProvider;

class DocumentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/config.php', 'documentation');
    }

    public function boot(): void
    {
        $this->commands([\Modules\Documentation\Console\Commands\ImportDocumentation::class, \Modules\Documentation\Console\Commands\RecoverPublications::class]);
        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) {
            $schedule->command('documentation:recover --no-interaction')->everyFiveMinutes();
        });
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'documentation');
    }
}
