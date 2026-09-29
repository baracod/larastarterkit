<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Modules\Admin\Services\SettingService;
use Modules\Auth\Models\User;
use Tests\TestCase;

class SettingCacheTest extends TestCase
{
    public function test_system_list_reflects_created_and_updated_settings(): void
    {
        $service = app(SettingService::class);
        $service->getSystemSettings();
        Cache::put('unrelated-cache', 'preserved', 3600);
        $service->set('cache-test', 'before');
        $this->assertSame('before', $service->getSystemSettings()->firstWhere('key', 'cache-test')->value);
        $service->set('cache-test', 'after');
        $this->assertSame('after', $service->getSystemSettings()->firstWhere('key', 'cache-test')->value);
        $this->assertSame('preserved', Cache::get('unrelated-cache'));
    }

    public function test_module_lists_reflect_created_and_updated_settings(): void
    {
        $service = app(SettingService::class);
        $service->getModuleSettings();
        $service->getSettingsByModule('CacheFixture');
        $service->getModulesList();
        $service->set('cache-test', 'before', 'module', 'CacheFixture');
        $this->assertContains('CacheFixture', $service->getModulesList()->all());
        $this->assertSame('before', $service->getModuleSettings()->firstWhere('key', 'cache-test')->value);
        $this->assertSame('before', $service->getModuleSettings('CacheFixture')->firstWhere('key', 'cache-test')->value);
        $service->set('cache-test', 'after', 'module', 'CacheFixture');
        $this->assertSame('after', $service->getModuleSettings()->firstWhere('key', 'cache-test')->value);
        $this->assertSame('after', $service->getSettingsByModule('CacheFixture')->firstWhere('key', 'cache-test')->value);
    }

    public function test_user_lists_reflect_created_and_updated_settings(): void
    {
        $service = app(SettingService::class);
        $user = User::factory()->create();
        $service->getUserSettings();
        $service->getUserSettings($user->id);
        $service->set('cache-test', 'before', 'user', userId: $user->id);
        $this->assertSame('before', $service->getUserSettings()->firstWhere('key', 'cache-test')->value);
        $this->assertSame('before', $service->getUserSettings($user->id)->firstWhere('key', 'cache-test')->value);
        $service->set('cache-test', 'after', 'user', userId: $user->id);
        $this->assertSame('after', $service->getUserSettings()->firstWhere('key', 'cache-test')->value);
        $this->assertSame('after', $service->getUserSettings($user->id)->firstWhere('key', 'cache-test')->value);
    }
}
