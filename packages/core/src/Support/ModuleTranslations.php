<?php

namespace Baracod\Larastarterkit\Core\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use Illuminate\Translation\Translator;

/**
 * Registers the translations of a core module. The module files are always loaded; files of the application's
 * legacy override directory (resources/lang/modules/<module>) replace their keys one by one instead of the whole
 * namespace, so keys added by a newer core version stay available.
 */
final class ModuleTranslations
{
    public static function register(Application $app, string $namespace, string $modulePath, string $overridePath): void
    {
        $register = function (Translator $translator) use ($namespace, $modulePath, $overridePath) {
            $translator->addNamespace($namespace, $modulePath);
            $translator->addJsonPath($modulePath);

            if (! is_dir($overridePath)) {
                return;
            }

            $translator->addJsonPath($overridePath);

            foreach (self::groups($modulePath, $overridePath) as [$locale, $group]) {
                $lines = array_replace_recursive(self::read("{$modulePath}/{$locale}/{$group}.php"), self::read("{$overridePath}/{$locale}/{$group}.php"));
                $translator->addLines(Arr::dot([$group => $lines]), $locale, $namespace);
            }
        };

        $app->afterResolving('translator', $register);
        if ($app->resolved('translator')) {
            $register($app['translator']);
        }
    }

    /**
     * @return list<array{string, string}>
     */
    private static function groups(string ...$paths): array
    {
        $groups = [];
        foreach ($paths as $path) {
            foreach (glob("{$path}/*/*.php") ?: [] as $file) {
                $groups[basename(dirname($file)).'/'.basename($file, '.php')] = [basename(dirname($file)), basename($file, '.php')];
            }
        }

        return array_values($groups);
    }

    private static function read(string $file): array
    {
        $lines = is_file($file) ? require $file : [];

        return is_array($lines) ? $lines : [];
    }
}
