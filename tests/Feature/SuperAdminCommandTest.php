<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\User;
use Tests\TestCase;

final class SuperAdminCommandTest extends TestCase
{
    private function runCommand(array $options = [])
    {
        return $this->artisan('starter:super-admin', $options);
    }

    public function test_it_creates_super_admin_with_full_access(): void
    {
        $permissionCount = DB::table('auth_permissions')->count();
        $this->assertGreaterThan(0, $permissionCount, 'Le catalogue doit être seedé (TestFixtures).');

        $this->runCommand([
            '--name' => 'Root Admin',
            '--email' => 'root.e2e@starter.test',
            '--password' => 'Secret!123',
        ])->assertExitCode(0);

        $user = User::where('email', 'root.e2e@starter.test')->firstOrFail();

        $this->assertSame('Root Admin', $user->name);
        $this->assertTrue($user->active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('administrator'));
        $this->assertSame(
            $permissionCount,
            DB::table('auth_role_permissions')->where('role_id', $user->roles()->value('auth_roles.id'))->count()
        );
    }

    public function test_it_is_idempotent(): void
    {
        $options = [
            '--name' => 'Root Admin',
            '--email' => 'root.idem@starter.test',
            '--password' => 'Secret!123',
        ];

        $this->runCommand($options)->assertExitCode(0);
        $this->runCommand($options)->assertExitCode(0);

        $this->assertSame(1, User::where('email', 'root.idem@starter.test')->count());
        $this->assertSame(
            1,
            DB::table('auth_user_roles')
                ->join('auth_users', 'auth_users.id', '=', 'auth_user_roles.user_id')
                ->where('auth_users.email', 'root.idem@starter.test')
                ->count()
        );
    }

    public function test_reset_access_rebuilds_the_role_grant(): void
    {
        $this->runCommand([
            '--name' => 'Root Admin',
            '--email' => 'root.reset@starter.test',
            '--password' => 'Secret!123',
        ])->assertExitCode(0);

        $roleId = DB::table('auth_roles')->where('name', 'administrator')->value('id');
        DB::table('auth_role_permissions')->where('role_id', $roleId)->limit(5)->delete();

        $this->runCommand([
            '--email' => 'root.reset@starter.test',
            '--password' => 'Secret!123',
            '--reset-access' => true,
        ])->assertExitCode(0);

        $this->assertSame(
            DB::table('auth_permissions')->count(),
            DB::table('auth_role_permissions')->where('role_id', $roleId)->count()
        );
    }

    public function test_existing_user_access_can_be_initialized_without_changing_password(): void
    {
        $user = User::create([
            'name' => 'Existing User',
            'username' => 'existing-admin',
            'email' => 'existing@starter.test',
            'password' => 'Original!123',
            'active' => false,
        ]);

        $this->runCommand(['--email' => $user->email])->assertExitCode(0);

        $user->refresh();
        $this->assertTrue(Hash::check('Original!123', $user->password));
        $this->assertTrue($user->active);
        $this->assertTrue($user->hasRole('administrator'));
    }

    public function test_it_fails_without_email_and_password_in_non_interactive_mode(): void
    {
        $this->runCommand(['--name' => 'X'])->assertExitCode(1);
    }

    public function test_it_rejects_invalid_email(): void
    {
        $this->runCommand([
            '--email' => 'pas-un-email',
            '--password' => 'Secret!123',
        ])->assertExitCode(1);

        $this->assertSame(0, User::where('email', 'pas-un-email')->count());
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->runCommand([
            '--name' => 'Root Admin',
            '--email' => 'short-password@starter.test',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertSame(0, User::where('email', 'short-password@starter.test')->count());
    }

    public function test_it_prompts_for_missing_options(): void
    {
        $this->runCommand()
            ->expectsQuestion('Email unique du super administrateur', 'asked@starter.test')
            ->expectsQuestion('Nom complet du super administrateur', 'Asked Admin')
            ->expectsQuestion('Mot de passe du super administrateur', 'Asked!Secret9')
            ->assertExitCode(0);

        $user = User::where('email', 'asked@starter.test')->firstOrFail();
        $this->assertSame('Asked Admin', $user->name);
        $this->assertTrue($user->hasRole('administrator'));
    }
}
