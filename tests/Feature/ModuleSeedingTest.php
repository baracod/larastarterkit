<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Models\Setting;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Tests\TestCase;

class ModuleSeedingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Simulate an empty catalogue inside the test transaction.
        foreach (['auth_role_permissions', 'auth_user_roles', 'auth_permissions', 'auth_roles', 'admin_settings'] as $table) {
            DB::table($table)->delete();
        }
    }

    public function test_module_seeders_create_roles_and_permissions_without_duplicates_or_resetting_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = Role::where('name', 'administrator')->firstOrFail();
        $this->assertSame(2, Role::count());
        $this->assertSame(24, Permission::count());
        $this->assertSame(24, $admin->permissions()->count());
        $this->assertSame(3, Setting::count());
        $this->assertSame(0, User::count());
        $this->assertDatabaseHas('auth_permissions', ['key' => 'browse_auth_users', 'action' => 'browse', 'subject' => 'auth_users']);
        $this->assertDatabaseHas('auth_permissions', ['key' => 'access_documentation', 'is_public' => true]);
        Setting::where('key', 'theme')->update(['value' => 'dark']);
        $admin->permissions()->detach(Permission::where('key', 'access_admin')->value('id'));

        $this->seed(DatabaseSeeder::class);
        $this->assertSame(2, Role::count());
        $this->assertSame(24, Permission::count());
        $this->assertSame(24, DB::table('auth_role_permissions')->count());
        $this->assertSame(3, Setting::count());
        $this->assertSame('dark', Setting::where('key', 'theme')->value('value'));
    }

    public function test_module_seed_succeeds_with_admin_before_auth_and_is_idempotent(): void
    {
        foreach ([1, 2] as $run) {
            Artisan::call('module:seed', [
                'module' => ['Admin', 'Documentation', 'Auth'],
                '--force' => true, '--no-interaction' => true,
            ]);
            $this->assertSame(2, Role::count(), Artisan::output());
            $this->assertSame(24, Permission::count(), Artisan::output());
            $this->assertSame(24, DB::table('auth_role_permissions')->count());
            $this->assertSame(3, Setting::count());
            $this->assertDatabaseHas('auth_permissions', ['key' => 'access_admin']);
        }
    }

    public function test_disabled_installed_modules_keep_their_permissions_without_seeding_application_data(): void
    {
        $this->mock(ModuleRegistry::class, function ($mock): void {
            $mock->makePartial();
            $mock->shouldReceive('statuses')->andReturn([
                'Auth' => true, 'Admin' => true, 'Documentation' => false,
            ]);
        });
        $this->mock(\Modules\Documentation\Database\Seeders\DocumentationDatabaseSeeder::class)
            ->shouldNotReceive('__invoke');
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(24, Permission::count());
        $this->assertDatabaseHas('auth_permissions', ['key' => 'access_admin']);
        $this->assertDatabaseHas('auth_permissions', ['key' => 'access_documentation', 'is_public' => true]);
        $this->assertSame(24, Role::where('name', 'administrator')->firstOrFail()->permissions()->count());
        $this->assertTrue(User::factory()->create()->can('access', 'documentation'));
        $this->assertFalse(app(ModuleRegistry::class)->enabled('Documents'));
        $this->assertFalse(app(ModuleRegistry::class)->enabled('Documentation'));
    }

    public function test_absent_modules_do_not_add_permissions(): void
    {
        $this->mock(ModuleRegistry::class, function ($mock): void {
            $mock->shouldReceive('statuses')->andReturn(['Auth' => true, 'Admin' => true]);
        });
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(18, Permission::count());
        $this->assertDatabaseMissing('auth_permissions', ['subject' => 'documents']);
        $this->assertDatabaseMissing('auth_permissions', ['subject' => 'documentation']);
        $this->assertSame(18, Role::where('name', 'administrator')->firstOrFail()->permissions()->count());
    }
}
