<?php

namespace Modules\Auth\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Modules\Auth\Services\AbilityService;
use Tests\TestCase;

class SuperAdminCommandsTest extends TestCase
{
    public function test_auth_command_creates_a_super_admin_with_present_and_future_access(): void
    {
        $this->artisan('auth:super-admin:create', [
            '--name' => 'Super Admin', '--email' => 'super-admin@example.test',
            '--password' => 'Secret!123', '--no-interaction' => true,
        ])->assertSuccessful();

        $user = User::where('email', 'super-admin@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Secret!123', $user->password));
        $this->assertTrue($user->hasRole('administrator'));
        $this->assertSame(Permission::count(), $user->roles->first()->permissions()->count());
        Permission::create(['key' => 'publish_future', 'action' => 'publish', 'subject' => 'future']);
        $this->assertTrue($user->can('publish', 'future'));
        Gate::define('future-policy', fn () => false);
        $this->assertTrue(Gate::forUser($user)->allows('future-policy'));
        $this->assertSame([['action' => 'manage', 'subject' => 'all']], app(AbilityService::class)->buildRulesForUser($user));
    }

    public function test_password_reset_revokes_only_target_credentials_and_preserves_roles(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'auth_sessions', 'session.connection' => null]);
        $user = $this->superAdmin();
        $other = $this->superAdmin();
        $oldRememberToken = $user->remember_token;
        $user->update(['must_change_password' => true, 'active' => false]);
        foreach ([$user, $other] as $account) {
            $account->createToken('test');
            DB::table('auth_password_reset_tokens')->insert([
                'email' => $account->email, 'token' => 'old-reset-token', 'created_at' => now(),
            ]);
            DB::table('auth_sessions')->insert([
                'id' => 'session-'.$account->id, 'user_id' => $account->id,
                'payload' => '', 'last_activity' => time(),
            ]);
        }

        $this->artisan('auth:super-admin:password', [
            '--email' => $user->email, '--password' => 'NewSecret!123', '--no-interaction' => true,
        ])->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret!123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertFalse($user->active);
        $this->assertTrue($user->hasRole('administrator'));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('auth_sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('auth_password_reset_tokens', ['email' => $user->email]);
        $this->assertSame(1, $other->tokens()->count());
        $this->assertDatabaseHas('auth_sessions', ['user_id' => $other->id]);
        $this->assertDatabaseHas('auth_password_reset_tokens', ['email' => $other->email]);
    }

    public function test_password_reset_rejects_regular_and_missing_accounts(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        foreach ([$user->email, 'missing@example.test'] as $email) {
            $this->artisan('auth:super-admin:password', [
                '--email' => $email, '--password' => 'NewSecret!123', '--no-interaction' => true,
            ])->assertFailed();
        }
        $this->assertSame($original, $user->fresh()->password);
        $this->assertFalse($user->hasRole('administrator'));
        $this->assertSame(1, User::count());
    }

    public function test_password_reset_requires_valid_inputs_without_interaction(): void
    {
        $user = $this->superAdmin();
        $original = $user->password;
        foreach ([[], ['--email' => 'invalid'], ['--email' => $user->email], ['--email' => $user->email, '--password' => 'weak'], ['--email' => $user->email, '--password' => 'Aa1!'.str_repeat('é', 35)]] as $options) {
            $this->artisan('auth:super-admin:password', $options + ['--no-interaction' => true])->assertFailed();
        }
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_password_can_be_entered_and_confirmed_with_hidden_prompts(): void
    {
        $user = $this->superAdmin();
        $this->artisan('auth:super-admin:password')
            ->expectsQuestion('Email du super administrateur', $user->email)
            ->expectsQuestion('Nouveau mot de passe du super administrateur', 'Prompted!123')
            ->expectsQuestion('Confirmez le nouveau mot de passe', 'Prompted!123')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('Prompted!123', $user->fresh()->password));
    }

    public function test_mismatching_password_confirmation_preserves_the_old_password(): void
    {
        $user = $this->superAdmin();
        $original = $user->password;
        $this->artisan('auth:super-admin:password', ['--email' => $user->email])
            ->expectsQuestion('Nouveau mot de passe du super administrateur', 'Prompted!123')
            ->expectsQuestion('Confirmez le nouveau mot de passe', 'Different!123')
            ->assertFailed();
        $this->assertSame($original, $user->fresh()->password);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'administrator')->firstOrFail());

        return $user;
    }
}
