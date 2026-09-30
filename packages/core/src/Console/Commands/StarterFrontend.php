<?php

namespace Baracod\Larastarterkit\Core\Console\Commands;

use Baracod\Larastarterkit\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StarterFrontend extends Command
{
    protected $signature = 'larastarterkit:frontend {--check} {--fingerprint}';

    protected $description = 'Compose JavaScript dependency requirements from installed Composer modules';

    public function handle(ModuleRegistry $registry): int
    {
        $paths = array_column($registry->packages(), 'path');
        foreach ($registry->all() as $module) {
            if ($module['package'] === null) {
                $paths[] = $module['path'];
            }
        }
        $dependencies = [];
        foreach (array_unique($paths) as $path) {
            if (! is_file($path.'/package.json')) {
                continue;
            }
            $json = json_decode(file_get_contents($path.'/package.json'), true, 512, JSON_THROW_ON_ERROR);
            foreach ($json['dependencies'] ?? [] as $name => $constraint) {
                if (isset($dependencies[$name]) && $dependencies[$name] !== $constraint) {
                    $this->error("Conflicting JavaScript requirement for {$name}: {$dependencies[$name]} versus {$constraint}. Align the module manifests.");

                    return self::FAILURE;
                }
                $dependencies[$name] = $constraint;
            }
        }
        ksort($dependencies);
        $versions = array_map(fn (array $package) => $package['version'], $registry->packages());
        ksort($versions);
        $payload = json_encode(['larastarterkit' => $versions, 'name' => 'larastarterkit-frontend', 'version' => '1.0.0', 'private' => true, 'dependencies' => $dependencies], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $fingerprint = hash('sha256', $payload);
        if ($this->option('fingerprint')) {
            $this->line($fingerprint);

            return self::SUCCESS;
        }
        $file = base_path('.larastarterkit/frontend/package.json');
        if ($this->option('check')) {
            $build = public_path('build/larastarterkit.json');
            if (! is_dir(base_path('node_modules')) && is_file($build)) {
                $built = json_decode(file_get_contents($build), true, 512, JSON_THROW_ON_ERROR);

                return hash_equals($fingerprint, $built['fingerprint'] ?? '') ? self::SUCCESS : self::FAILURE;
            }
            if (! is_file($file) || file_get_contents($file) !== $payload) {
                $this->error('Run larastarterkit:frontend and pnpm install to synchronize frontend dependencies.');

                return self::FAILURE;
            }
            $installed = base_path('node_modules/larastarterkit-frontend/package.json');
            if (! is_file($installed) || json_decode(file_get_contents($installed), true) !== json_decode($payload, true)) {
                $this->error('Run pnpm install: installed JavaScript requirements do not match Composer packages.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }
        File::ensureDirectoryExists(dirname($file));
        File::put($file, $payload);
        $this->writeTypeScriptConfig($registry);
        $this->info('Frontend dependency manifest generated. Run pnpm install before building.');

        return self::SUCCESS;
    }

    private function writeTypeScriptConfig(ModuleRegistry $registry): void
    {
        $frontend = realpath(dirname(__DIR__, 3).'/frontend');
        $paths = [
            '@/*' => [$frontend.'/resources/ts/*'],
            '@core/*' => [$frontend.'/resources/ts/@core/*'],
            '@core' => [$frontend.'/resources/ts/@core'],
            '@layouts/*' => [$frontend.'/resources/ts/@layouts/*'],
            '@layouts' => [$frontend.'/resources/ts/@layouts'],
            '@themeConfig' => [$frontend.'/themeConfig.ts'],
            '@images/*' => [$frontend.'/resources/images/*'],
            '@styles/*' => [$frontend.'/resources/styles/*'],
            '@app/*' => [base_path('resources/ts/*')],
        ];
        foreach ($registry->all() as $name => $module) {
            // Same containment rule as the Vite registry: the frontend path must stay inside its module.
            $root = realpath($module['path']);
            $source = realpath($module['path'].'/'.($module['frontend']['path'] ?? 'resources/ts'));
            if ($root && $source && str_starts_with($source, $root.DIRECTORY_SEPARATOR)) {
                $paths['@'.strtolower($name).'/*'] = [$source.'/*'];
            }
        }
        $config = ['compilerOptions' => [
            'target' => 'ESNext',
            'module' => 'ESNext',
            'moduleResolution' => 'Bundler',
            'baseUrl' => '..',
            'paths' => $paths,
            'jsx' => 'preserve',
            'resolveJsonModule' => true,
            'esModuleInterop' => true,
            'isolatedModules' => true,
            'lib' => ['ESNext', 'DOM', 'DOM.Iterable'],
            'types' => ['vite/client', 'unplugin-vue-router/client', 'vite-plugin-vue-layouts/client'],
        ]];
        File::put(base_path('.larastarterkit/tsconfig.json'), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }
}
