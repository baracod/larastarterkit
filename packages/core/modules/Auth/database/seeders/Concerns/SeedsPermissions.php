<?php

namespace Modules\Auth\Database\Seeders\Concerns;

use Modules\Auth\Database\Seeders\RoleSeeder;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;

trait SeedsPermissions
{
    /** @param array<string, list<string>> $subjects */
    protected function seedPermissions(array $subjects, array $publicSubjects = []): void
    {
        $this->callSilent(RoleSeeder::class);
        $administrator = Role::query()->where('name', 'administrator')->firstOrFail();
        $ids = [];
        foreach ($subjects as $subject => $actions) {
            foreach ($actions as $action) {
                $permission = Permission::query()->firstOrCreate(['key' => $action.'_'.$subject], [
                    'action' => $action, 'subject' => $subject, 'description' => $action.' '.$subject,
                    'is_public' => in_array($subject, $publicSubjects, true), 'always_allow' => false,
                ]);
                $ids[] = $permission->id;
            }
        }
        $administrator->permissions()->syncWithoutDetaching($ids);
    }
}
