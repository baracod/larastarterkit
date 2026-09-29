<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Retrouve le modèle Eloquent qui porte une table, dans tous les modules
 * (définitions module.json puis classes présentes dans Modules/{Module}/app/Models et app/Models).
 */
final class RelationResolver
{
    /** @var array<string, array{name:string,namespace:string,fqcn:string,path:string,moduleName:string}>|null */
    private static ?array $index = null;

    /**
     * @return array{name:string,namespace:string,fqcn:string,path:string,moduleName:string}|null
     */
    public static function forTable(string $table): ?array
    {
        return self::index()[$table] ?? null;
    }

    /**
     * Relation belongsTo complète au format des définitions.
     *
     * @return array<string, mixed>|null
     */
    public static function belongsTo(string $foreignKey, string $table, string $currentModule, ?string $currentTable = null, ?array $selfModel = null, string $ownerKey = 'id'): ?array
    {
        $model = $table === $currentTable ? $selfModel : self::forTable($table);
        if ($model === null) {
            return null;
        }

        return [
            'type' => 'belongsTo',
            'name' => Str::camel(Str::beforeLast($foreignKey, '_id') ?: Str::singular($table)),
            'foreignKey' => $foreignKey,
            'table' => $table,
            'ownerKey' => $ownerKey,
            'moduleName' => $model['moduleName'],
            'externalModule' => $model['moduleName'] !== $currentModule,
            'model' => array_intersect_key($model, array_flip(['name', 'namespace', 'fqcn', 'path'])),
            'isParentHasMany' => false,
        ];
    }

    /** Vide le cache (après création d'un modèle). */
    public static function flush(): void
    {
        self::$index = null;
    }

    /** @return array<string, array{name:string,namespace:string,fqcn:string,path:string,moduleName:string}> */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }
        $index = [];

        $installed = app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->all();
        foreach ($installed as $installedModule) {
            $file = $installedModule['path'].'/module.json';
            $json = json_decode((string) File::get($file), true);
            foreach ((array) ($json['models'] ?? []) as $model) {
                if (! is_array($model) || empty($model['tableName']) || empty($model['name'])) {
                    continue;
                }
                $module = (string) ($model['moduleName'] ?? basename(dirname($file)));
                $namespace = (string) ($model['namespace'] ?? "Modules\\{$module}\\Models");
                $index[$model['tableName']] = [
                    'name' => $model['name'],
                    'namespace' => $namespace,
                    'fqcn' => (string) ($model['fqcn'] ?? $namespace.'\\'.$model['name']),
                    'path' => (string) ($model['path'] ?? base_path("Modules/{$module}/app/Models/{$model['name']}.php")),
                    'moduleName' => $module,
                ];
            }
        }

        $directories = array_merge(
            array_map(static fn (array $module) => [$module['path'].'/app/Models', $module['name']], array_values($installed)),
            [[app_path('Models'), null]],
        );
        foreach ($directories as [$directory, $module]) {
            if (! File::isDirectory($directory)) {
                continue;
            }
            foreach (File::allFiles($directory) as $file) {
                $code = (string) File::get($file->getPathname());
                if (! preg_match('/^namespace\s+([^;]+);/m', $code, $ns) || ! preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $code, $class)) {
                    continue;
                }
                // Table explicite, sinon convention Eloquent (pluriel snake_case).
                $table = preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $code, $t) ? $t[1] : Str::snake(Str::pluralStudly($class[1]));
                $index[$table] ??= [
                    'name' => $class[1],
                    'namespace' => $ns[1],
                    'fqcn' => $ns[1].'\\'.$class[1],
                    'path' => $file->getPathname(),
                    'moduleName' => $module ?? 'App',
                ];
            }
        }

        return self::$index = $index;
    }
}
