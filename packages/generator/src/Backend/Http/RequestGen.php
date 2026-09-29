<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition as DField;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

/**
 * Générateur de FormRequest pour un modèle d'un module.
 *
 * - Source : Modules/{Module}/module.json (via DefinitionStore)
 * - Règles : présence, type, unique, exists, énumérations, longueur, puis règles complémentaires
 *   (heuristiques, éventuellement enrichies par l'IA et filtrées par une liste blanche).
 * - Toutes les valeurs sont échappées : une définition modifiée à la main ne peut pas injecter de code.
 */
final class RequestGen
{
    private string $moduleName;

    private DFModel $modelDef;

    private string $modelName;

    private string $requestNamespace;

    private string $requestPath;

    /** Provenance des règles complémentaires ("IA" ou "règles"). */
    public string $rulesSource = 'règles';

    public function __construct(private string $modelKey, string $moduleName)
    {
        $this->moduleName = Str::studly($moduleName);
        $jsonPath = Module::getModulePath($this->moduleName).'module.json';
        if (! File::exists($jsonPath)) {
            throw new RuntimeException("Fichier de définition introuvable: {$jsonPath}");
        }

        $this->modelDef = DefinitionStore::fromFile($jsonPath)->module()->model($this->modelKey);
        $this->modelName = $this->modelDef->name();
        $this->requestNamespace = "Modules\\{$this->moduleName}\\Http\\Requests";
        $this->requestPath = base_path("Modules/{$this->moduleName}/app/Http/Requests/{$this->modelName}Request.php");
    }

    public function requestPath(): string
    {
        return $this->requestPath;
    }

    /**
     * Écrit la FormRequest. L'état "hasRequest" est enregistré par l'orchestrateur.
     */
    public function generate(): bool
    {
        $stubPath = dirname(__DIR__, 2).'/Backend/Stubs/backend/Request.stub';
        if (! File::exists($stubPath)) {
            throw new RuntimeException("Stub introuvable: {$stubPath}");
        }

        $outcome = app(Assistant::class)->validationRules($this->modelDef);
        $this->rulesSource = $outcome->source();

        $content = strtr(File::get($stubPath), [
            '{{ namespace }}' => $this->requestNamespace,
            '{{ modelName }}' => $this->modelName,
            '{{ validationRules }}' => $this->renderRules($this->buildRules($outcome->value)),
        ]);

        File::ensureDirectoryExists(\dirname($this->requestPath));
        File::put($this->requestPath, $content);

        return true;
    }

    /**
     * Règles par champ, chaque règle étant une expression PHP prête à écrire.
     *
     * @param  array<string, list<string>>  $extraRules
     * @return array<string, list<string>>
     */
    public function buildRules(array $extraRules = []): array
    {
        $belongsToByFk = [];
        foreach ($this->modelDef->relations() as $relation) {
            if (($relation['type'] ?? null) === 'belongsTo' && ! empty($relation['foreignKey']) && ! empty($relation['table'])) {
                $belongsToByFk[(string) $relation['foreignKey']] = [(string) $relation['table'], (string) ($relation['ownerKey'] ?? 'id')];
            }
        }

        $rules = [];
        foreach ($this->modelDef->fields() as $field) {
            if (! $field instanceof DField || in_array($field->name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $list = [self::str($field->isNullable() ? 'nullable' : 'required')];

            $custom = trim((string) $field->customizedType);
            if ($custom !== '') {
                // Règles personnalisées écrites dans la définition : conservées telles quelles, mais échappées.
                foreach (array_filter(array_map('trim', explode('|', $custom))) as $rule) {
                    $list[] = self::str($rule);
                }
            } else {
                foreach ($this->typeRules($field) as $rule) {
                    $list[] = $rule;
                }
            }

            if (isset($belongsToByFk[$field->name])) {
                [$table, $ownerKey] = $belongsToByFk[$field->name];
                $list[] = 'Rule::exists('.self::str($table).', '.self::str($ownerKey).')';
            }
            if ($field->unique) {
                $list[] = 'Rule::unique('.self::str($this->modelDef->tableName()).', '.self::str($field->name).')->ignore($this->recordId())';
            }
            foreach ($extraRules[$field->name] ?? [] as $rule) {
                $list[] = self::str($rule);
            }

            $rules[$field->name] = array_values(array_unique($list));
        }

        return $rules;
    }

    /** @return list<string> */
    private function typeRules(DField $field): array
    {
        return match ($field->type) {
            FieldType::String, FieldType::Text => [self::str('string')],
            FieldType::Integer => [self::str('integer')],
            FieldType::Float => [self::str('numeric')],
            FieldType::Boolean => [self::str('boolean')],
            FieldType::Date, FieldType::DateTime => [self::str('date')],
            FieldType::Json => [self::str('array')],
            FieldType::Uuid => [self::str('uuid')],
            FieldType::Enum => $field->enumValues === []
                ? [self::str('string')]
                : [self::str('string'), 'Rule::in(['.implode(', ', array_map(self::str(...), $field->enumValues)).'])'],
        };
    }

    /** @param array<string, list<string>> $rules */
    private function renderRules(array $rules): string
    {
        $lines = [];
        foreach ($rules as $field => $list) {
            $lines[] = '            '.self::str($field).' => ['.implode(', ', $list).'],';
        }

        return implode("\n", $lines);
    }

    private static function str(string $value): string
    {
        return var_export($value, true);
    }
}
