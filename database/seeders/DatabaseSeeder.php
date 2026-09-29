<?php

namespace Database\Seeders;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Database\Seeder;
use Modules\Admin\Database\Seeders\AdminDatabaseSeeder;
use Modules\Auth\Database\Seeders\AuthDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AuthDatabaseSeeder::class, AdminDatabaseSeeder::class]);

        foreach (app(ModuleRegistry::class)->statuses() as $module => $enabled) {
            if (in_array($module, ['Auth', 'Admin'], true)) {
                continue;
            }
            // Disabled modules need their access catalogue, but not their application data.
            $class = $enabled ? $module.'DatabaseSeeder' : 'PermissionSeeder';
            $seeder = "Modules\\{$module}\\Database\\Seeders\\{$class}";
            if (class_exists($seeder)) {
                $this->call($seeder);
            }
        }
    }
}
