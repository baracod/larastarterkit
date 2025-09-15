<?php

namespace Baracod\Larastarterkit;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Baracod\Larastarterkit\Commands\LarastarterkitCommand;

class LarastarterkitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
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
            ->hasCommand(LarastarterkitCommand::class);
    }
}
