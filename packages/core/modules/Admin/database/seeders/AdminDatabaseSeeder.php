<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;

class AdminDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, SettingSeeder::class]);
    }
}
