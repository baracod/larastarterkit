<?php

namespace Baracod\Larastarterkit;

use Baracod\Larastarterkit\Commands\LarastarterkitCommand;
use Baracod\Larastarterkit\Commands\LarastarterkitInstallCommand;
use Illuminate\Support\Str;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LarastarterkitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/modules.php', 'modules');

        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('larastarterkit')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_larastarterkit_table')
            ->hasCommand(LarastarterkitCommand::class)
            ->hasCommand(LarastarterkitInstallCommand::class);
    }

    public function boot(): void
    {
        parent::boot();

        // ajoute le macro smartPlural à Str
        Str::macro('smartPlural', function ($word) {
            $uncountable = ['cursus', 'status', 'syllabus'];

            return in_array(strtolower($word), $uncountable, true) ? $word : Str::plural($word);
        });

        // Allow user to publish the customized laravel-modules config from this package
        $this->publishes([
            __DIR__.'/../config/modules.php' => config_path('modules.php'),
        ], 'larastarterkit-modules-config');

        // (optionnel) register a convenience tag that groups stubs  config if you want:
        // $this->publishes([
        //     __DIR__ . '/../../Stubs' => base_path('stubs/larastarterkit'),
        //     __DIR__ . '/../config/modules.php' => config_path('modules.php'),
        // ], 'larastarterkit-all');

        $this->publishes([
            __DIR__.'/../Stubs' => base_path('stubs/larastarterkit'),
        ], 'larastarterkit-stubs');
    }
}
