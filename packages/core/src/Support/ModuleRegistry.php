<?php

namespace Baracod\Larastarterkit\Core\Support;

use Composer\InstalledVersions;
use LogicException;

class ModuleRegistry
{
    private ?array $packages = null;

    public function __construct(private ?string $localModulesPath = null) {}

    public function packages(): array
    {
        return $this->packages ??= $this->cachedPackages();
    }

    /**
     * Scanning every installed composer.json is costly: the result is kept in
     * bootstrap/cache until Composer rewrites vendor/composer/installed.php.
     */
    private function cachedPackages(): array
    {
        $installed = dirname((new \ReflectionClass(InstalledVersions::class))->getFileName()).'/installed.php';
        $stamp = is_file($installed) ? realpath($installed).':'.filemtime($installed).':'.filesize($installed) : null;
        $cache = base_path('bootstrap/cache/larastarterkit-packages.json');
        if ($stamp !== null && is_file($cache)) {
            $data = json_decode((string) file_get_contents($cache), true);
            if (is_array($data) && ($data['stamp'] ?? null) === $stamp) {
                return $data['packages'];
            }
        }
        $packages = $this->scanPackages();
        if ($stamp !== null && is_dir(dirname($cache)) && is_writable(dirname($cache))) {
            $temporary = $cache.'.'.bin2hex(random_bytes(4)).'.tmp';
            if (@file_put_contents($temporary, json_encode(['stamp' => $stamp, 'packages' => $packages], JSON_UNESCAPED_SLASHES)) !== false) {
                @rename($temporary, $cache);
            }
            @unlink($temporary);
        }

        return $packages;
    }

    private function scanPackages(): array
    {
        $packages = [];
        foreach (InstalledVersions::getInstalledPackages() as $name) {
            $path = InstalledVersions::getInstallPath($name);
            if (! $path || ! is_file($path.'/composer.json')) {
                continue;
            }
            $json = json_decode(file_get_contents($path.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            if (isset($json['extra']['larastarterkit'])) {
                $packages[$name] = [...$json['extra']['larastarterkit'], 'path' => $path, 'version' => ltrim(InstalledVersions::getPrettyVersion($name), 'v')];
            }
        }

        return $packages;
    }

    public function all(): array
    {
        $candidates = [];
        foreach ($this->packages() as $package => $meta) {
            foreach ($meta['modules'] ?? [] as $relative) {
                $path = realpath($meta['path'].'/'.$relative);
                if (! $path || ($path !== realpath($meta['path']) && ! str_starts_with($path, realpath($meta['path']).DIRECTORY_SEPARATOR))) {
                    throw new LogicException("Invalid module path in {$package}");
                }
                $candidates[] = [$path, $package];
            }
        }
        foreach (glob(($this->localModulesPath ?? base_path('Modules')).'/*/module.json') ?: [] as $file) {
            $candidates[] = [realpath(dirname($file)), null];
        }
        $modules = [];
        $names = [];
        foreach ($candidates as [$path, $package]) {
            $data = json_decode(file_get_contents($path.'/module.json'), true, 512, JSON_THROW_ON_ERROR);
            $name = $data['name'] ?? '';
            if (! preg_match('/^[A-Z][A-Za-z0-9]+$/', $name)) {
                throw new LogicException("Invalid module name in {$path}");
            }
            $key = strtolower($name);
            if (isset($names[$key])) {
                if ($names[$key] !== $path) {
                    throw new LogicException("Duplicate module: {$name}");
                }

                continue; // Local authoring symlink to the same installed package.
            }
            $names[$key] = $path;
            $modules[$name] = [...$data, 'path' => $path, 'package' => $package];
        }
        foreach ($modules as $name => $module) {
            foreach ($module['requires'] ?? [] as $dependency) {
                if (! isset($modules[$dependency])) {
                    throw new LogicException("Module {$name} requires {$dependency}");
                }
            }
        }

        return $modules;
    }

    public function statuses(?array $override = null): array
    {
        $file = base_path('modules_statuses.json');
        $statuses = is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
        $statuses = $override ?? $statuses;
        $result = [];
        $modules = $this->all();
        foreach ($modules as $name => $module) {
            $result[$name] = in_array($name, ['Auth', 'Admin'], true) || ($statuses[$name] ?? false) === true;
        }
        foreach ($modules as $name => $module) {
            foreach ($module['requires'] ?? [] as $dependency) {
                if ($result[$name] && ! $result[$dependency]) {
                    throw new LogicException("Enabled module {$name} requires enabled module {$dependency}");
                }
            }
        }

        return $result;
    }

    public function enabled(string $name): bool
    {
        return $this->statuses()[$name] ?? false;
    }

    public function assertLocal(string $name): void
    {
        $module = $this->all()[$name] ?? null;
        if ($module && $module['package'] !== null) {
            throw new LogicException("{$name} is managed by Composer. Create a local module to extend it.");
        }
    }
}
