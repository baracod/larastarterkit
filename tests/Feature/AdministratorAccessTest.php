<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Notifications\LoginAlert;
use Modules\Auth\Services\AbilityService;
use Tests\TestCase;

class AdministratorAccessTest extends TestCase
{
    public function test_administrator_has_every_permission_without_individual_grants(): void
    {
        $user = $this->administratorWithoutGrants();
        $permission = Permission::create(['key' => 'future_feature', 'action' => 'publish', 'subject' => 'future_feature']);

        $this->assertTrue($user->can('publish', 'future_feature'));
        $this->assertTrue($user->can('a_future_permission'));
        $this->assertTrue($user->permissions()->whereKey($permission->id)->exists());
        $this->assertSame(Permission::count(), $user->permissions()->count());
        Gate::define('restricted-test', fn () => false);
        $this->assertTrue(Gate::forUser($user)->allows('restricted-test'));
        $this->assertTrue(Gate::forUser($user)->allows('viewHorizon'));
        $this->assertFalse($user->cannot('restricted-test'));
        $this->assertSame([['action' => 'manage', 'subject' => 'all']], app(AbilityService::class)->buildRulesForUser($user));
    }

    public function test_administrator_can_list_modules_in_both_admin_screens(): void
    {
        Sanctum::actingAs($this->administratorWithoutGrants());
        $this->getJson('/api/v1/admin/modules')->assertOk()->assertJsonCount(3)
            ->assertJsonFragment(['name' => 'Admin'])->assertJsonMissing(['name' => 'Documents']);
        $this->getJson('/api/v1/admin/settings/available-modules')->assertOk()
            ->assertJsonPath('data', ['Admin', 'Auth', 'Documentation']);
    }

    public function test_login_returns_full_administrator_access_without_permission_pivots(): void
    {
        Notification::fake();
        $user = $this->administratorWithoutGrants();
        config(['sanctum.stateful' => ['localhost']]);
        $this->withHeader('Origin', 'http://localhost')->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.abilityRules', [['action' => 'manage', 'subject' => 'all']])
            ->assertJsonCount(Permission::count(), 'data.permissions')
            ->assertJsonPath('data.roles.0.name', 'administrator');
        Notification::assertSentTo($user, LoginAlert::class);
    }

    public function test_regular_users_keep_their_assigned_permissions_and_cannot_access_administration(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'limited-module-tester', 'display_name' => 'Limited']);
        $granted = Permission::create(['key' => 'granted_module_test', 'action' => 'browse', 'subject' => 'module_test']);
        $denied = Permission::create(['key' => 'denied_module_test', 'action' => 'edit', 'subject' => 'module_test']);
        $user->roles()->attach($role);
        $role->permissions()->attach($granted);
        $this->assertTrue($user->can('browse', 'module_test'));
        $this->assertFalse($user->can('edit', 'module_test'));
        $this->assertFalse($user->permissions()->whereKey($denied->id)->exists());
        Gate::define('restricted-test', fn () => false);
        $this->assertFalse(Gate::forUser($user)->allows('restricted-test'));
        $this->assertNotContains(['action' => 'manage', 'subject' => 'all'], app(AbilityService::class)->buildRulesForUser($user));
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/modules')->assertForbidden();
        $this->getJson('/api/v1/admin/settings/available-modules')->assertForbidden();
    }

    public function test_removing_administrator_role_revokes_implicit_access(): void
    {
        $user = $this->administratorWithoutGrants();
        $this->assertTrue($user->can('future_permission'));
        $user->roles()->detach();
        $this->assertFalse($user->can('future_permission'));
        $this->assertFalse(Gate::forUser($user)->allows('future_permission'));
        $this->assertNotContains(['action' => 'manage', 'subject' => 'all'], app(AbilityService::class)->buildRulesForUser($user));
    }

    private function administratorWithoutGrants(): User
    {
        $role = Role::where('name', 'administrator')->firstOrFail();
        $role->permissions()->detach();
        $user = User::factory()->create(['password' => 'password', 'active' => true, 'must_change_password' => false]);
        $user->roles()->attach($role);

        return $user;
    }
}
