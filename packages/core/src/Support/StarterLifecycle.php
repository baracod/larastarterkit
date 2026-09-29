<?php

namespace Baracod\Larastarterkit\Core\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StarterLifecycle
{
    public function pending(): array
    {
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles([...$migrator->paths(), database_path('migrations')]);
        $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

        return array_values(array_diff(array_keys($files), $ran));
    }

    public function upgrade(): void
    {
        if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new RuntimeException('Migration failed; no seeders were executed.');
        }
        // Every seeder listed here must preserve user data and be safe to retry.
        DB::transaction(function () {
            foreach (app(ModuleRegistry::class)->all() as $name => $module) {
                if (! app(ModuleRegistry::class)->enabled($name)) {
                    continue;
                }
                $seeders = $module['upgradeSeeders'] ?? [];
                foreach ($seeders as $class) {
                    if (! is_subclass_of($class, \Illuminate\Database\Seeder::class)) {
                        throw new RuntimeException("Invalid upgrade seeder: {$class}");
                    }
                    app($class)->__invoke();
                }
            }
        });
    }
}
