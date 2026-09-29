<?php

namespace Baracod\Larastarterkit\Generator\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PackageCommand extends Command
{
    protected $signature = 'larastarterkit:package {module} {package : vendor/name}';

    protected $description = 'Prepare a local module as a Composer package including its frontend';

    public function handle(ModuleRegistry $registry): int
    {
        $name = $this->argument('module');
        $package = $this->argument('package');
        if (! preg_match('/^[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*$/', $package)) {
            $this->error('Use vendor/package in lowercase.');

            return self::FAILURE;
        }
        $registry->assertLocal($name);
        $module = $registry->all()[$name] ?? null;
        if (! $module) {
            $this->error('Unknown local module.');

            return self::FAILURE;
        }
        $path = $module['path'].'/composer.json';
        $composer = is_file($path) ? json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR) : [];
        $composer['name'] = $package;
        $composer['type'] = 'library';
        $composer['require']['baracod/larastarterkit-core'] = '^1.0@RC';
        $composer['autoload']['psr-4'] ??= [];
        foreach (['' => 'app/', 'Database\\Factories\\' => 'database/factories/', 'Database\\Seeders\\' => 'database/seeders/'] as $suffix => $directory) {
            $composer['autoload']['psr-4']["Modules\\{$name}\\{$suffix}"] = $directory;
        }
        $composer['extra']['larastarterkit']['modules'] = ['.'];
        File::put($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        $this->info('Module prepared. Publish the entire module directory, including resources/ts. Declare JavaScript dependencies in its package.json.');

        return self::SUCCESS;
    }
}
