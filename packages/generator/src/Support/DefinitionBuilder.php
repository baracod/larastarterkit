<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Support;

use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Crée ou remplace la définition d'un modèle dans Modules/{Module}/module.json.
 */
final class DefinitionBuilder
{
    /**
     * @param  list<FieldDefinition>  $fields
     * @param  list<array{foreignKey:string,table:string,ownerKey?:string}>  $foreignKeys
     * @param  array<string, mixed>  $options  softDeletes, description…
     * @return array{model: ModelDefinition, skippedRelations: list<string>}
     */
    public static function write(string $module, string $modelName, string $table, array $fields, array $foreignKeys = [], array $options = [], bool $replace = false): array
    {
        $module = Str::studly($module);
        app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->assertLocal($module);
        $modelName = Str::studly($modelName);
        $path = base_path("Modules/{$module}/module.json");
        if (! File::exists($path)) {
            throw new RuntimeException("Le module « {$module} » n'existe pas (module.json introuvable).");
        }
        if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table)) {
            throw new RuntimeException("Nom de table invalide : « {$table} ».");
        }

        $store = DefinitionStore::fromFile($path);
        $key = Str::kebab($modelName);
        if (! $replace && array_key_exists($key, $store->module()->all())) {
            throw new RuntimeException("Le modèle « {$key} » existe déjà dans {$module}. Utilisez --force pour le remplacer.");
        }

        $namespace = "Modules\\{$module}\\Models";
        $model = ModelDefinition::fromArray([
            'key' => $key,
            'name' => $modelName,
            'namespace' => $namespace,
            'tableName' => $table,
            'moduleName' => $module,
            'path' => base_path("Modules/{$module}/app/Models/{$modelName}.php"),
            'fqcn' => $namespace.'\\'.$modelName,
            'fillable' => array_map(static fn (FieldDefinition $f) => $f->toArray(), $fields),
            'relations' => [],
        ]);

        $self = ['name' => $modelName, 'namespace' => $namespace, 'fqcn' => $namespace.'\\'.$modelName, 'path' => (string) $model->path(), 'moduleName' => $module];
        $relations = [];
        $skipped = [];
        foreach ($foreignKeys as $fk) {
            $relation = RelationResolver::belongsTo($fk['foreignKey'], $fk['table'], $module, $table, $self, $fk['ownerKey'] ?? 'id');
            $relation === null ? $skipped[] = "{$fk['foreignKey']} → {$fk['table']}" : $relations[] = $relation;
        }
        $model->setRelations($relations);
        foreach ($options as $option => $value) {
            $model->setOption($option, $value);
        }

        $store->module()->upsertModel($model);
        $store->save($path);
        RelationResolver::flush();

        return ['model' => $model, 'skippedRelations' => $skipped];
    }
}
