<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->firstOrCreate(['name' => 'administrator'], ['display_name' => 'Administrator', 'order' => 0]);
        Role::query()->firstOrCreate(['name' => 'user'], ['display_name' => 'User', 'order' => 100]);
    }
}
