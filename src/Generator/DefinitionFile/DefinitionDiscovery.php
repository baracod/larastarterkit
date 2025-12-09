<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile;

use DomainException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use FilesystemIterator;
use SplFileInfo;

use function base_path;
use function class_exists;
use function fnmatch;
use function is_dir;

/**
 * Class DefinitionDiscovery
 *
 * Localise et charge toutes les définitions de modules (fichiers JSON) dans un ou
 * plusieurs dossiers racines. Idéal pour agréger les définitions NWIDART/Laravel Modules.
 *
 * @example
 * use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionDiscovery;
 *
 * // Charger tous les modules à partir du dossier "Modules/"
 * $stores = DefinitionDiscovery::loadAllStores(
 *     roots: [base_path('Modules')],
 *     patterns: ['module.json', '*.module.json', 'definition.json']
 * );
 *
 * // Récupérer directement les ModuleDefinition, avec gestion des doublons
 * $modulesByName = DefinitionDiscovery::loadAllModules(
 *     roots: [base_path('Modules')],
 *     onDuplicate: 'throw' // 'throw' | 'skip' | 'suffix'
 * );
 */
final class DefinitionDiscovery
{
    /**
     * Liste tous les chemins de fichiers JSON de modules détectés.
     *
     * @param  string[]|string   $roots     Dossier ou liste de dossiers à explorer (ex: base_path('Modules')).
     * @param  string[]          $patterns  Noms de fichiers ciblés (glob simple via fnmatch).
     * @param  bool              $recursive Explorer récursivement (true par défaut).
     * @param  string[]          $ignore    Dossiers à ignorer (par nom de base).
     * @return list<string>                 Chemins absolus des fichiers trouvés.
     *
     * @example
     * $files = DefinitionDiscovery::findJsonFiles(base_path('Modules'));
     */
    public static function findJsonFiles(
        array|string $roots,
        array $patterns = ['module.json', '*.module.json', 'definition.json'],
        bool $recursive = true,
        array $ignore = ['vendor', 'node_modules', '.git', 'storage', 'bootstrap']
    ): array {
        $roots = is_array($roots) ? $roots : [$roots];

        $files = [];

        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);

            // Filtre pour exclure vendor/node_modules/etc.
            $filter = new RecursiveCallbackFilterIterator($dir, static function (SplFileInfo $file, $key, $iterator) use ($ignore) {
                if ($file->isDir()) {
                    return !in_array($file->getBasename(), $ignore, true);
                }
                return true;
            });

            $iterator = $recursive
                ? new RecursiveIteratorIterator($filter)
                : $filter;

            foreach ($iterator as $entry) {
                /** @var SplFileInfo $entry */
                if (!$entry->isFile()) {
                    continue;
                }

                $basename = $entry->getBasename();

                foreach ($patterns as $pattern) {
                    if (fnmatch($pattern, $basename)) {
                        $files[] = $entry->getPathname();
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Charge tous les DefinitionStore à partir de ROOTS/PATTERNS donnés.
     *
     * @param  string[]|string $roots
     * @param  string[]        $patterns
     * @param  callable|null   $onError   Callback (optionnel) en cas d’erreur: function(string $file, \Throwable $e): void
     * @return array<string, DefinitionStore>  Tableau indexé par chemin de fichier.
     *
     * @throws DomainException En cas d’erreur de lecture si aucun $onError n’est fourni.
     *
     * @example
     * $stores = DefinitionDiscovery::loadAllStores([base_path('Modules')]);
     */
    public static function loadAllStores(
        array|string|null $roots = null,
        array $patterns = ['module.json', '*.module.json', 'definition.json'],
        ?callable $onError = null
    ): array {
        $stores = [];
        if (empty($roots))
            $roots = base_path('Modules');
        $files  = self::findJsonFiles($roots, $patterns);

        foreach ($files as $file) {
            try {
                $stores[$file] = DefinitionStore::fromFile($file);
            } catch (\Throwable $e) {
                if ($onError) {
                    $onError($file, $e);
                    continue;
                }
                throw new DomainException("Échec de chargement du fichier {$file}: {$e->getMessage()}", previous: $e);
            }
        }

        return $stores;
    }

    /**
     * Charge tous les ModuleDefinition trouvés, indexés par nom de module.
     *
     * Gestion des doublons :
     *  - 'throw'  : lève une exception si deux JSON déclarent le même module.
     *  - 'skip'   : ignore les suivants et conserve le premier.
     *  - 'suffix' : suffixe le nom en double avec '#2', '#3', ...
     *
     * @param  string[]|string $roots
     * @param  string[]        $patterns
     * @param  'throw'|'skip'|'suffix' $onDuplicate
     * @param  callable|null   $onError Callback optionnel: function(string $file, \Throwable $e): void
     * @return array<string, ModuleDefinition>  Clé = nom (éventuellement suffixé en mode 'suffix')
     *
     * @throws DomainException En cas de doublon (mode 'throw') ou de lecture invalide sans $onError.
     *
     * @example
     * $modules = DefinitionDiscovery::loadAllModules(base_path('Modules'), onDuplicate: 'suffix');
     */
    public static function loadAllModules(
        array|string $roots,
        array $patterns = ['module.json', '*.module.json', 'definition.json'],
        string $onDuplicate = 'throw',
        ?callable $onError = null
    ): array {
        $stores  = self::loadAllStores($roots, $patterns, $onError);
        $modules = [];

        foreach ($stores as $path => $store) {
            $name = $store->module()->name();

            if (isset($modules[$name])) {
                if ($onDuplicate === 'skip') {
                    // on garde le premier, on ignore le suivant
                    continue;
                }
                if ($onDuplicate === 'suffix') {
                    $i = 2;
                    $candidate = "{$name}#{$i}";
                    while (isset($modules[$candidate])) {
                        $i++;
                        $candidate = "{$name}#{$i}";
                    }
                    $modules[$candidate] = $store->module();
                    continue;
                }

                // Par défaut : throw
                throw new DomainException("Doublon de module détecté: '{$name}' via '{$path}'.");
            }

            $modules[$name] = $store->module();
        }

        return $modules;
    }

    /**
     * Variante pratique : renvoie une Collection Laravel si disponible.
     *
     * @param  string[]|string $roots
     * @param  string[]        $patterns
     * @param  'throw'|'skip'|'suffix' $onDuplicate
     * @return \Illuminate\Support\Collection<string, ModuleDefinition>|array<string, ModuleDefinition>
     */
    public static function collectAllModules(
        array|string $roots,
        array $patterns = ['module.json', '*.module.json', 'definition.json'],
        string $onDuplicate = 'throw'
    ): mixed {
        $modules = self::loadAllModules($roots, $patterns, $onDuplicate);

        if (class_exists(\Illuminate\Support\Collection::class)) {
            /** @phpstan-ignore-next-line */
            return collect($modules);
        }

        return $modules;
    }
}
