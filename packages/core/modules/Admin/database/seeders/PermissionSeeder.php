<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\Concerns\SeedsPermissions;

class PermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $this->seedPermissions(['admin' => ['access'], 'dashboard' => ['access']], ['dashboard']);
    }
}
