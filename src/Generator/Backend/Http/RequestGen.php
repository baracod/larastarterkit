<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use RuntimeException;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Illuminate\Support\Facades\File;
use Baracod\Larastarterkit\Generator\Traits\StubResolverTrait;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition as DField;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;

/**
 * Générateur de FormRequest pour un modèle d'un module NWIDART.
 *
 * - Source: ModuleData/{module}.json (via DefinitionStore)
 * - Lit DFModel (name, fields, relations) pour produire les règles + messages
 * - Écrit la FormRequest dans Modules/{Module}/app/Http/Requests/{Model}Request.php
 * - Met à jour backend.hasRequest = true dans le store JSON et persiste
 */
final class RequestGen
{
    use StubResolverTrait;
    private string $moduleName;       // Studly (ex: Blog)
    private string $modelKey;         // kebab (ex: blog-author)

    private DFModel $modelDef;        // Définition typée du modèle
    private DefinitionStore $store;   // Store du module

    private string $modelName;        // ex: BlogAuthor
    private string $requestNamespace; // ex: Modules\Blog\Http\Requests
    private string $requestPath;      // ex: Modules/Blog/app/Http/Requests/BlogAuthorRequest.php

    /** Chemin du JSON (ModuleData/blog.json) */
    private string $jsonPath;

    /**
     * @param string $modelKey   Clé du modèle dans le JSON (kebab-case)
     * @param string $moduleName Nom du module (Studly/kebab accepté, normalisé en Studly)
     * @throws RuntimeException si store ou modèle introuvable
     */
    public function __construct(string $modelKey, string $moduleName)
    {
        $this->modelKey   = $modelKey;
        $this->moduleName = Str::studly($moduleName);
        $this->jsonPath   = self::jsonPath($this->moduleName);

        if (!File::exists($this->jsonPath)) {
            throw new RuntimeException("Fichier de définition introuvable: {$this->jsonPath}");
        }

        // Charge store + modèle typé
        $this->store    = DefinitionStore::fromFile($this->jsonPath);
        $this->modelDef = $this->store->module()->model($this->modelKey);

        $this->modelName        = $this->modelDef->name();
        $this->requestNamespace = "Modules\\{$this->moduleName}\\Http\\Requests";
        $this->requestPath      = base_path("Modules/{$this->moduleName}/app/Http/Requests/{$this->modelName}Request.php");
    }

    /**
     * Génère la classe de FormRequest, met à jour le store (hasRequest=true).
     */
    public function generate(): bool
    {
        // 1) Règles + messages
        $rulesByField  = $this->buildRulesFromDefinition($this->modelDef);
        $rulesBlock    = $this->formatValidationRules($rulesByField);
        $messagesBlock = $this->generateMessagesFromRulesArray($rulesByField);

        // 2) Charger le stub
        $stubPath = $this->resolveStubPath('backend/Request.stub');

        if (!File::exists($stubPath)) {
            throw new RuntimeException("Le stub `stubs/entity-generator/Request.stub` est introuvable.");
        }
        $stubContent = File::get($stubPath);

        // 3) Remplacements
        $content = str_replace(
            ['{{ namespace }}', '{{ modelName }}', '{{ validationRules }}', '{{ errorMessages }}'],
            [$this->requestNamespace, $this->modelName, $rulesBlock, $messagesBlock],
            $stubContent
        );

        // 4) Écriture
        File::ensureDirectoryExists(\dirname($this->requestPath));
        File::put($this->requestPath, $content);

        // 5) Mettre à jour backend.hasRequest = true et persister
        $this->modelDef->backend()->hasRequest = true;
        $this->store->module()->upsertModel($this->modelDef);
        $this->store->save($this->jsonPath);

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Constructions règles/messages depuis DFModel
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Construit les règles de validation à partir des champs/relations du DFModel.
     *
     * @return array<string,string> ex: ['title' => "'required|string|max:255'"]
     */
    private function buildRulesFromDefinition(DFModel $model): array
    {
        // Index des belongsTo par foreignKey pour le "exists:table,ownerKey"
        $belongsToByFk = [];
        foreach (($model->relations() ?? []) as $rel) {
            if (($rel['type'] ?? null) === 'belongsTo' && !empty($rel['foreignKey'])) {
                $belongsToByFk[(string)$rel['foreignKey']] = [
                    'table'    => $rel['table']    ?? null,
                    'ownerKey' => $rel['ownerKey'] ?? 'id',
                ];
            }
        }

        $rules = [];

        foreach ($model->fields() as $field) {
            if (!$field instanceof DField) {
                continue;
            }

            $name           = $field->name;
            if ($name === '') continue;

            $rawType        = $field->type->value;     // enum FieldType
            $customizedType = trim((string)($field->customizedType ?? ''));
            $defaultValue   = $field->defaultValue ?? null;

            // nullable si defaultValue == null / "NULL" ou nom “technique”
            $forceNullableByName = in_array(strtolower($name), ['id', 'hash', 'uuid'], true);
            $nullable = $forceNullableByName || $this->isNullish($defaultValue);

            $fieldRules = [];
            $fieldRules[] = $nullable ? 'nullable' : 'required';

            // exists:table,ownerKey si belongsTo détecté
            if (isset($belongsToByFk[$name]) && !empty($belongsToByFk[$name]['table'])) {
                $refTable = $belongsToByFk[$name]['table'];
                $ownerKey = $belongsToByFk[$name]['ownerKey'] ?? 'id';
                $fieldRules[] = "exists:{$refTable},{$ownerKey}";
            }

            // Mapping type → règles (customizedType prioritaire)
            $mapped = $this->mapFieldTypeToRule($rawType, $customizedType);
            if ($mapped !== null) {
                foreach (explode('|', $mapped) as $rule) {
                    $r = trim($rule);
                    if ($r !== '') $fieldRules[] = $r;
                }
            }

            $rules[$name] = "'" . implode('|', array_unique($fieldRules)) . "'";
        }

        return $rules;
    }

    private function isNullish(mixed $val): bool
    {
        if ($val === null) return true;
        if (is_string($val) && strtoupper(trim($val)) === 'NULL') return true;
        return false;
    }

    /**
     * Map FieldType enum (+ customizedType) → règles Laravel.
     */
    private function mapFieldTypeToRule(string $fieldType, string $customizedType): ?string
    {
        if ($customizedType !== '') {
            // On permet "string|max:255" / "email" / "array" etc.
            return $customizedType;
        }

        return match (strtolower($fieldType)) {
            'integer'   => 'integer',
            'float'     => 'numeric',
            'boolean'   => 'boolean',
            'date'      => 'date',
            'datetime'  => 'date',              // ou date_format si tu forces un format
            'json'      => 'json',              // si tu veux un tableau: 'array'
            'text'      => 'string',
            'string'    => 'string',
            default     => 'string',
        };
    }

    /** @param array<string,string> $rules */
    private function formatValidationRules(array $rules): string
    {
        $lines = array_map(
            fn($k, $v) => "            '{$k}' => {$v},",
            array_keys($rules),
            $rules
        );
        return "[\n" . implode("\n", $lines) . "\n        ];";
    }

    /** @param array<string,string> $rulesByColumn */
    private function generateMessagesFromRulesArray(array $rulesByColumn): string
    {
        $messages = [];
        foreach ($rulesByColumn as $name => $ruleString) {
            $rules = explode('|', trim($ruleString, "'\" "));
            foreach ($rules as $rule) {
                $rule = trim($rule);
                if ($rule === '') continue;
                $ruleName = strtolower(explode(':', $rule)[0]);

                $messages[] = match ($ruleName) {
                    'required'     => "            '{$name}.required' => 'Le champ {$name} est obligatoire.',",
                    'string'       => "            '{$name}.string' => 'Le champ {$name} doit être une chaîne de caractères.',",
                    'max'          => "            '{$name}.max' => 'Le champ {$name} dépasse la longueur maximale autorisée.',",
                    'min'          => "            '{$name}.min' => 'Le champ {$name} est trop court.',",
                    'integer'      => "            '{$name}.integer' => 'Le champ {$name} doit être un entier.',",
                    'numeric'      => "            '{$name}.numeric' => 'Le champ {$name} doit être un nombre.',",
                    'date'         => "            '{$name}.date' => 'Le champ {$name} doit être une date valide.',",
                    'date_format'  => "            '{$name}.date_format' => 'Le champ {$name} doit respecter le format requis.',",
                    'before'       => "            '{$name}.before' => 'Le champ {$name} doit être une date antérieure.',",
                    'boolean'      => "            '{$name}.boolean' => 'Le champ {$name} doit être vrai ou faux.',",
                    'email'        => "            '{$name}.email' => 'Le champ {$name} doit être une adresse email valide.',",
                    'url'          => "            '{$name}.url' => 'Le champ {$name} doit être une URL valide.',",
                    'uuid'         => "            '{$name}.uuid' => 'Le champ {$name} doit être un UUID valide.',",
                    'exists'       => "            '{$name}.exists' => 'La valeur du champ {$name} est invalide.',",
                    'unique'       => "            '{$name}.unique' => 'Le champ {$name} doit être unique.',",
                    default        => null,
                };
            }
        }

        $body = implode("\n", array_filter(array_unique($messages)));

        return <<<PHP
            public function messages(): array
            {
                return [
            $body
                ];
            }
        PHP;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Utilitaires
    // ─────────────────────────────────────────────────────────────────────────────

    private static function jsonPath(string $moduleName, bool $ensureDir = false): string
    {
        $path = Module::getModulePath($moduleName) . 'module.json';
        return $path;
    }
}
