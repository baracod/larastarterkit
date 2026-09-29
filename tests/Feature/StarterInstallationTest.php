<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;
use Tests\TestCase;

class StarterInstallationTest extends TestCase
{
    public function test_fresh_installation_has_only_generic_tables_and_idempotent_catalog(): void
    {
        foreach (Schema::getTableListing() as $table) {
            $this->assertDoesNotMatchRegularExpression('/^(base_|extraction_|negoce_|tracker_)/', $table);
        }
        $this->assertTrue(Schema::hasTable('procedure_documents'));
        $count = Permission::query()->count();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame($count, Permission::query()->count());
        $this->assertSame(0, User::query()->count());
        $this->getJson('/api/v1/modules')->assertOk()->assertExactJson(['Auth' => true, 'Admin' => true, 'Documentation' => true]);
    }

    public function test_user_module_settings_are_generic_and_authorized(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/users/'.$other->id.'/modules')->assertForbidden();
        $payload = ['settings' => [['module' => 'Admin', 'key' => 'preferences', 'value' => ['layout' => 'compact']]]];
        $this->putJson('/api/v1/auth/users/'.$user->id.'/modules', $payload)->assertForbidden();
        $this->actingAsAdministrator();
        $this->putJson('/api/v1/auth/users/'.$user->id.'/modules', $payload)->assertOk();
        $this->putJson('/api/v1/auth/users/'.$user->id.'/modules', $payload)->assertOk();
        $this->assertSame(1, $user->moduleSettings()->count());
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/users/'.$user->id.'/modules')->assertOk()->assertJsonPath('data.settings.0.value.layout', 'compact');
    }

    public function test_shared_documents_are_available_without_a_documents_module(): void
    {
        $this->actingAsAdministrator();
        $this->assertFalse(app(ModuleRegistry::class)->enabled('Documents'));
        $this->getJson('/api/v1/documents/policies')->assertOk();
        $this->getJson('/api/v1/auth/user')->assertOk();
        $this->getJson('/api/v1/admin/modules')->assertOk()->assertJsonMissing(['name' => 'Documents']);
        $this->postJson('/api/v1/admin/modules/Documents/toggle')->assertNotFound();
    }

    public function test_primary_modules_cannot_be_disabled(): void
    {
        $this->actingAsAdministrator();
        foreach (['Auth', 'Admin'] as $name) {
            $this->postJson('/api/v1/admin/modules/'.$name.'/toggle')->assertForbidden();
        }
    }
}
