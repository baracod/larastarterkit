<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\SettingSeeder;
use Modules\Admin\Models\Setting;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    public function test_login_from_the_application_own_host_is_stateful_whatever_the_port(): void
    {
        Notification::fake();
        $user = $this->administrator();
        config(['app.url' => 'http://localhost:8000']);

        $this->withHeader('Origin', 'http://127.0.0.1:8001')
            ->withServerVariables(['HTTP_HOST' => '127.0.0.1:8001'])
            ->postJson('http://127.0.0.1:8001/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_login_without_session_returns_an_explicit_error_instead_of_a_server_error(): void
    {
        $user = $this->administrator();
        config(['sanctum.stateful' => ['first-party.test']]);

        $this->withHeader('Origin', 'http://third-party.test')
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(419)->assertJsonPath('success', false);
    }

    public function test_roles_can_be_cleared_and_invalid_payloads_return_validation_errors(): void
    {
        Sanctum::actingAs($this->administrator());
        $target = User::factory()->create();
        $target->roles()->attach(Role::create(['name' => 'audit-tmp', 'display_name' => 'Tmp']));

        $this->postJson("/api/v1/auth/users/{$target->id}/roles", ['roles' => []])->assertOk();
        $this->assertSame(0, $target->roles()->count());
        $this->postJson("/api/v1/auth/users/{$target->id}/roles", [])->assertUnprocessable();
    }

    public function test_api_resources_do_not_expose_html_form_routes(): void
    {
        foreach (['auth-user', 'auth-role', 'auth-permission'] as $name) {
            $this->assertFalse(Route::has("api.{$name}.create"));
            $this->assertFalse(Route::has("api.{$name}.edit"));
            $this->assertTrue(Route::has("api.{$name}.index"));
        }
    }

    public function test_role_validation_messages_use_readable_field_names(): void
    {
        Sanctum::actingAs($this->administrator());

        $this->postJson('/api/v1/auth/roles', ['name' => 'no-display-name'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.display_name.message', 'Le champ « nom d\'affichage » est obligatoire.');
    }

    public function test_system_settings_get_readable_labels_without_overwriting_custom_ones(): void
    {
        Setting::query()->where('type', 'system')->where('key', 'theme')->update(['label' => 'theme']);
        Setting::query()->where('type', 'system')->where('key', 'language')->update(['label' => 'Ma langue']);

        $this->seed(SettingSeeder::class);

        $this->assertSame('Thème', Setting::query()->where('type', 'system')->where('key', 'theme')->value('label'));
        $this->assertSame('Ma langue', Setting::query()->where('type', 'system')->where('key', 'language')->value('label'));
    }

    public function test_doctor_warns_when_no_administrator_exists_without_failing(): void
    {
        User::query()->whereHas('roles', fn ($query) => $query->where('name', 'administrator'))->each(fn (User $user) => $user->roles()->detach());

        $this->assertSame(0, Artisan::call('larastarterkit:doctor', ['--json' => true]));
        $this->assertStringContainsString('auth:super-admin:create', Artisan::output());
    }

    private function administrator(): User
    {
        $user = User::factory()->create(['password' => 'password', 'active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::where('name', 'administrator')->firstOrFail());

        return $user;
    }
}
