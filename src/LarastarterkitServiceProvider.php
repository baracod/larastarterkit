<?php

namespace Baracod\Larastarterkit;

use Baracod\Larastarterkit\Commands\LarastarterkitCommand;
use Illuminate\Support\Str;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LarastarterkitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {

        Str::macro('smartPlural', function ($word) {
            $uncountable = ['cursus', 'status', 'syllabus'];

            return in_array(strtolower($word), $uncountable) ? $word : Str::plural($word);
        });
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
