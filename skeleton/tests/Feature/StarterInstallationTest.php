<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Baracod\Larastarterkit\Core\Support\StarterLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Models\User;
use Tests\TestCase;

class StarterInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_initializes_without_a_default_administrator(): void
    {
        app(StarterLifecycle::class)->upgrade();
        $statuses = app(ModuleRegistry::class)->statuses();
        $this->assertTrue($statuses['Auth']);
        $this->assertTrue($statuses['Admin']);
        $this->assertSame(0, User::count());
        $this->getJson('/api/v1/admin/settings')->assertUnauthorized();
    }
}
