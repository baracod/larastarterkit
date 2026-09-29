<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\Concerns\SeedsPermissions;

class PermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $this->seedPermissions([
            'auth' => ['access'],
            'auth_users' => ['browse', 'read', 'add', 'edit', 'delete'],
            'auth_roles' => ['browse', 'read', 'add', 'edit', 'delete'],
            'auth_permissions' => ['browse', 'read', 'add', 'edit', 'delete'],
        ]);
    }
}
