<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Support;

use Composer\Autoload\ClassLoader;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Déclare les espaces de noms d'un module (app/, factories, seeders) dans composer.json
 * et dans l'autoloader courant, pour que les classes générées soient chargeables sans redémarrage.
 */
final class ModuleAutoload
{
    /**
     * @return array<string, string> Espaces de noms => dossiers du module
     */
    public static function mappings(string $module): array
    {
        return [
            "Modules\\{$module}\\" => "Modules/{$module}/app/",
            "Modules\\{$module}\\Database\\Factories\\" => "Modules/{$module}/database/factories/",
            "Modules\\{$module}\\Database\\Seeders\\" => "Modules/{$module}/database/seeders/",
        ];
    }

    /**
     * Ajoute les correspondances manquantes. Renvoie celles qui ont été ajoutées à composer.json.
     *
     * @return list<string>
     */
    public static function register(string $module, ?string $composerPath = null): array
    {
        $composerPath ??= base_path('composer.json');
        $composer = json_decode((string) File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $added = [];
        foreach (self::mappings($module) as $namespace => $directory) {
            if (! isset($composer['autoload']['psr-4'][$namespace])) {
                $composer['autoload']['psr-4'][$namespace] = $directory;
                $added[] = $namespace;
            }
            self::registerRuntime($namespace, base_path($directory));
        }
        if ($added !== []) {
            File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);
        }

        return $added;
    }

    /**
     * Régénère l'autoload Composer (nécessaire pour les autres processus : workers, serveur).
     */
    public static function dump(): bool
    {
        $process = new Process(['composer', 'dump-autoload', '--no-interaction', '--quiet'], base_path());
        $process->setTimeout(180);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Rend les classes d'un module chargeables dans le processus courant, sans modifier composer.json.
     */
    public static function registerForCurrentProcess(string $module): void
    {
        foreach (self::mappings($module) as $namespace => $directory) {
            self::registerRuntime($namespace, base_path($directory));
        }
    }

    private static function registerRuntime(string $namespace, string $directory): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            if (! array_key_exists($namespace, $loader->getPrefixesPsr4())) {
                $loader->addPsr4($namespace, $directory);
            }

            return;
        }
    }
}
