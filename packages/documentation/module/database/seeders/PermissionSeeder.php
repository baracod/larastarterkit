<?php

namespace Modules\Documentation\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\Database\Seeders\Concerns\SeedsPermissions;

class PermissionSeeder extends Seeder
{
    use SeedsPermissions;

    public function run(): void
    {
        $this->seedPermissions(['documentation' => ['access']], ['documentation']);
        $this->seedPermissions(['documentation' => ['browse', 'edit', 'media', 'publish', 'restore']]);
    }
}
