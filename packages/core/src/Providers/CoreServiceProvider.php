<?php

namespace Baracod\Larastarterkit\Core\Providers;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (glob(__DIR__.'/../../config/*.php') as $file) {
            $this->mergeConfigFrom($file, basename($file, '.php'));
        }
        $this->app->singleton(ModuleRegistry::class);
        config(['modules.scan.enabled' => true, 'modules.scan.paths' => array_values(array_unique(array_map('dirname', array_column(app(ModuleRegistry::class)->all(), 'path'))))]);
        $this->app->register(AppServiceProvider::class);
        $this->app->register(DocumentsServiceProvider::class);
        $this->app->register(HorizonServiceProvider::class);
    }

    public function boot(): void
    {
        $this->callAfterResolving('translation.loader', function ($loader) {
            $loader->addPath(__DIR__.'/../../frontend/resources/lang');
            $loader->addPath(lang_path());
        });
        $this->loadViewsFrom(__DIR__.'/../../views', 'larastarterkit');
        $this->app['view']->addLocation(__DIR__.'/../../views');
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Baracod\Larastarterkit\Core\Console\Commands\StarterFrontend::class,
                \Baracod\Larastarterkit\Core\Console\Commands\StarterDoctor::class,
                \Baracod\Larastarterkit\Core\Console\Commands\StarterInstall::class,
                \Baracod\Larastarterkit\Core\Console\Commands\StarterUpgrade::class,
                \Baracod\Larastarterkit\Core\Console\Commands\StarterModules::class,
                \Baracod\Larastarterkit\Core\Console\Commands\CheckRuntimeSqlAccessCommand::class,
                \Baracod\Larastarterkit\Core\Console\Commands\ProvisionRuntimeDatabase::class,
                \Baracod\Larastarterkit\Core\Console\Commands\RestoreDatabaseCommand::class,
            ]);
        }
    }
}
