<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Baracod\Larastarterkit\Core\Support\StarterLifecycle;
use Illuminate\Support\Facades\File;
use Modules\Admin\Models\Setting;
use Tests\TestCase;

class StarterPackagesTest extends TestCase
{
    public function test_optional_world_datasets_are_available_inside_the_admin_package(): void
    {
        $reader = new class
        {
            use \Modules\Admin\Database\Seeders\Concerns\LoadsJsonDataFiles;

            public function countries(): array
            {
                return $this->readJsonDataset('data/world/countries.json');
            }
        };
        $dataset = $reader->countries();
        $this->assertSame('countries', $dataset['table']);
        $this->assertNotEmpty($dataset['rows']);
        $this->assertDirectoryDoesNotExist(database_path('seeders/data/prod/world'));
    }

    public function test_core_modules_are_loaded_from_the_installed_package(): void
    {
        $registry = app(ModuleRegistry::class);
        $modules = $registry->all();
        $this->assertSame('baracod/larastarterkit-core', $modules['Auth']['package']);
        $this->assertSame(realpath(base_path('vendor/baracod/larastarterkit-core/modules/Auth')), $modules['Auth']['path']);
        $this->assertTrue($registry->statuses(['Auth' => false, 'Admin' => false])['Auth']);
        $this->assertFalse($registry->statuses([])['Documentation']);
        $this->expectException(\LogicException::class);
        $registry->assertLocal('Auth');
    }

    public function test_duplicate_modules_are_rejected_instead_of_shadowing_core_code(): void
    {
        $moduleRoot = sys_get_temp_dir().'/starter-registry-'.bin2hex(random_bytes(6));
        $directory = $moduleRoot.'/PackageCollisionFixture';
        $this->assertDirectoryDoesNotExist($directory);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/module.json', '{"name":"Auth","providers":[]}');
        try {
            $this->expectExceptionMessage('Duplicate module: Auth');
            (new ModuleRegistry($moduleRoot))->all();
        } finally {
            File::deleteDirectory($moduleRoot);
        }
    }

    public function test_missing_or_disabled_module_dependencies_are_rejected(): void
    {
        $moduleRoot = sys_get_temp_dir().'/starter-registry-'.bin2hex(random_bytes(6));
        $directory = $moduleRoot.'/DependencyFixture';
        $this->assertDirectoryDoesNotExist($directory);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/module.json', '{"name":"DependencyFixture","requires":["Documentation"],"providers":[]}');
        try {
            $this->expectExceptionMessage('requires enabled module Documentation');
            (new ModuleRegistry($moduleRoot))->statuses(['DependencyFixture' => true, 'Documentation' => false]);
        } finally {
            File::deleteDirectory($moduleRoot);
        }
    }

    public function test_upgrades_are_repeatable_and_preserve_custom_settings(): void
    {
        $setting = Setting::query()->where('type', 'system')->where('key', 'theme')->firstOrFail();
        $setting->update(['value' => 'custom-theme']);
        $lifecycle = app(StarterLifecycle::class);
        $before = Setting::count();
        $lifecycle->upgrade();
        $lifecycle->upgrade();
        $this->assertSame('custom-theme', $setting->refresh()->value);
        $this->assertSame($before, Setting::count());
        $this->assertSame([], $lifecycle->pending());
    }

    public function test_frontend_diagnostic_detects_drift_and_accepts_a_matching_production_build(): void
    {
        $originalBase = $this->app->basePath();
        $originalPublic = public_path();
        $temporary = sys_get_temp_dir().'/starter-frontend-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($temporary.'/public/build');
        $registry = \Mockery::mock(ModuleRegistry::class);
        $registry->shouldReceive('packages')->andReturn([]);
        File::ensureDirectoryExists($temporary.'/Modules/Demo/resources/ts');
        File::ensureDirectoryExists($temporary.'/Modules/Escape');
        $registry->shouldReceive('all')->andReturn([
            'Demo' => ['path' => $temporary.'/Modules/Demo', 'package' => 'vendor/demo'],
            'Escape' => ['path' => $temporary.'/Modules/Escape', 'package' => 'vendor/escape', 'frontend' => ['path' => '../Demo/resources/ts']],
        ]);
        $registry->shouldReceive('statuses')->andReturn(['Auth' => true, 'Admin' => true]);
        $this->app->instance(ModuleRegistry::class, $registry);
        $this->app->setBasePath($temporary);
        $this->app->usePublicPath($temporary.'/public');
        try {
            $this->artisan('larastarterkit:frontend', ['--check' => true])->assertFailed();
            $this->artisan('larastarterkit:frontend')->assertSuccessful();
            $config = json_decode(File::get($temporary.'/.larastarterkit/tsconfig.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('Bundler', $config['compilerOptions']['moduleResolution']);
            $this->assertSame([$temporary.'/resources/ts/*'], $config['compilerOptions']['paths']['@app/*']);
            $this->assertDirectoryExists(dirname($config['compilerOptions']['paths']['@/*'][0]));
            $this->assertSame([realpath($temporary.'/Modules/Demo/resources/ts').'/*'], $config['compilerOptions']['paths']['@demo/*']);
            $this->assertArrayNotHasKey('@escape/*', $config['compilerOptions']['paths']);
            $payload = File::get($temporary.'/.larastarterkit/frontend/package.json');
            File::put($temporary.'/public/build/larastarterkit.json', json_encode(['fingerprint' => hash('sha256', $payload)]));
            $this->artisan('larastarterkit:frontend', ['--check' => true])->assertSuccessful();
            File::put($temporary.'/public/build/larastarterkit.json', '{"fingerprint":"outdated"}');
            $this->artisan('larastarterkit:frontend', ['--check' => true])->assertFailed();
        } finally {
            $this->app->setBasePath($originalBase);
            $this->app->usePublicPath($originalPublic);
            File::deleteDirectory($temporary);
        }
    }

    public function test_module_can_be_prepared_for_composer_without_separate_frontend_package(): void
    {
        $moduleRoot = sys_get_temp_dir().'/starter-registry-'.bin2hex(random_bytes(6));
        $directory = $moduleRoot.'/DistributionFixture';
        $this->assertDirectoryDoesNotExist($directory);
        File::ensureDirectoryExists($directory);
        File::put($directory.'/module.json', '{"name":"DistributionFixture","providers":[]}');
        $this->app->instance(ModuleRegistry::class, new ModuleRegistry($moduleRoot));
        try {
            $this->artisan('larastarterkit:package', ['module' => 'DistributionFixture', 'package' => 'example/distribution-fixture', '--no-interaction' => true])->assertSuccessful();
            $composer = json_decode(File::get($directory.'/composer.json'), true);
            $this->assertSame(['.'], $composer['extra']['larastarterkit']['modules']);
            $this->assertSame('app/', $composer['autoload']['psr-4']['Modules\\DistributionFixture\\']);
        } finally {
            File::deleteDirectory($moduleRoot);
        }
    }
}
