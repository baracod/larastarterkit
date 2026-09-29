<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function actingAsAdministrator(string $email = 'test-admin@starter.local'): User
    {
        $role = Role::firstOrCreate(['name' => 'administrator'], ['display_name' => 'Administrator']);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Test Administrator',
                'username' => 'test-admin-'.substr(md5($email), 0, 8),
                'password' => 'password',
                'active' => true,
                'email_verified_at' => now(),
            ]
        );

        $attached = DB::table('auth_user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();

        if (! $attached) {
            DB::table('auth_user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
        }

        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
