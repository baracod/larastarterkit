<?php

namespace Baracod\Larastarterkit\Generator;

use Illuminate\Support\ServiceProvider;

class GeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Ai\GeneratorAi::class);
        foreach (glob(__DIR__.'/../config/*.php') as $file) {
            $this->mergeConfigFrom($file, basename($file, '.php'));
        }
        config(['modules.stubs.path' => __DIR__.'/../stubs/laravel-module']);
    }

    public function boot(): void
    {
        $this->commands([Commands\PackageCommand::class, Commands\LarastarterkitCommand::class, Commands\CrudCommand::class, Commands\DefinitionCommand::class, Commands\ModuleCommand::class, Commands\AiCommand::class]);
    }
}
