<?php

namespace Modules\Documentation\Database\Seeders;

use Illuminate\Database\Seeder;

class DocumentationDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
    }
}
