<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Frontend;

use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition as DField;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Baracod\Larastarterkit\Generator\IA\LanguageGenerator;
use Baracod\Larastarterkit\Generator\ModuleGenerator;
use Baracod\Larastarterkit\Generator\Utils\ConsoleTrait;
use Baracod\Larastarterkit\Generator\Utils\GeneratorTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\spin;

/**
 * Générateur TypeScript + Vue + services + i18n
 * piloté par DefinitionStore (ModuleData/{module-kebab}.json).
 *
 * Source: DefinitionStore -> DFModel (fields, relations, backend.meta...)
 */
final class TypeScriptGeneratorFromJson
{
    use ConsoleTrait;
    use GeneratorTrait;

    private string $moduleName;       // ex. Blog (Studly)

    private string $modelKey;         // ex. blog-author

    private DFModel $dfModel;         // définition typée

    private ModuleGenerator $module;  // util chemins module

    // Dérivés pratiques
    private string $modelName;        // ex. BlogAuthor

    private string $tableName;        // ex. blog_authors

    private string $apiDirectory;     // Modules/Blog/resources/ts/api/

    private string $typesFilePath;    // Modules/Blog/resources/ts/types/entities.d.ts

    private string $baseUrl;          // ex. blog/blog-authors (sans préfixe api/)

    private string $moduleNameLower;  // ex. blog

    /**
     * @param  string  $modelKey  ex. "blog-author"
     * @param  string  $moduleName  ex. "Blog" (Studly/kebab accepté)
     */
    public function __construct(string $modelKey, string $moduleName)
    {
        $this->moduleName = Str::studly($moduleName);
        $this->modelKey = $modelKey;

        // Chargement DefinitionStore + DFModel
        $jsonPath = $this->jsonPath($this->moduleName);

        $store = DefinitionStore::fromFile($jsonPath);
        $this->dfModel = $store->module()->model($this->modelKey);

        // Hydratation à partir du DFModel
        $this->modelName = $this->dfModel->name();
        $this->tableName = $this->dfModel->tableName() ?: Str::snake(Str::plural($this->modelName));

        $this->module = new ModuleGenerator($this->moduleName);
        $this->apiDirectory = $this->module->getPath('resources/ts/api/');
        $this->typesFilePath = $this->module->getPath('resources/ts/types/entities.d.ts');
        $this->moduleNameLower = Str::lower($this->moduleName);

        // Base URL: priorité au backend.apiRoute, sinon /{module}/{resource}
        $apiRoute = $this->dfModel->backend()->apiRoute ?? null;
        if (is_string($apiRoute) && $apiRoute !== '') {
            $base = $apiRoute;
        } else {
            // ressource = plural-kebab de la key (ex: blog-authors)
            $resource = method_exists(Str::class, 'smartPlural')
                ? Str::kebab(Str::smartPlural($this->modelKey))
                : Str::kebab(Str::plural($this->modelKey));

            $base = "{$this->moduleNameLower}/{$resource}";
        }

        // On veut une baseUrl SANS préfixe api/
        $base = Str::startsWith($base, 'api/') ? Str::after($base, 'api/') : $base;
        $this->baseUrl = Str::replaceFirst('api/', '', $base);
    }

    /** Point d’entrée */
    public function generate(): bool
    {
        // 1) Colonnes & relations issues du DFModel
        [$columns, $relations] = $this->columnsAndRelationsFromDefinition($this->dfModel);

        // 2) Interface TS
        $typeInterface = $this->mapColumnsToTypeScript($columns, $relations);
        $this->updateTypeDefinitionFile($typeInterface);

        // 3) API
        $this->generateApiServiceFile();

        // 4) Page index.vue
        $this->generateVuePageFile($columns);

        // 5) AddOrEdit.vue
        $this->generateAddOrEditComponent($columns, $relations);

        // 6) Menu
        $this->updateMenuItems('add');

        // 7) i18n
        spin(function () use ($columns) {
            $this->generateLangFileByAIFromDefinition($columns);
        }, 'Génération des fichiers de langue...');

        $this->consoleWriteSuccess("Génération terminée pour {$this->moduleName}::{$this->modelName}");

        return true;
    }

    /* ---------------------------------------------------------------------
     |  Lecture DefinitionStore / DFModel
     * --------------------------------------------------------------------*/

    private static function jsonPath(string $moduleName, bool $ensureDir = false): string
    {
        $path = Module::getModulePath($moduleName).'module.json';

        return $path;
    }

    /**
     * Construit une représentation "colonnes + relations" depuis DFModel.
     * Colonnes: objets stdClass ->Field, ->Type, ->Null (façon SHOW COLUMNS),
     * Relations: ['belongsTo'=>[], 'hasMany'=>[]] avec {model, field, table}
     *
     * @return array{0:array<int,object>,1:array{belongsTo:array<int,array>,hasMany:array<int,array>}}
     */
    private function columnsAndRelationsFromDefinition(DFModel $model): array
    {
        $columns = [];

        // Colonnes depuis FieldDefinition
        foreach ($model->fields() as $field) {
            if (! $field instanceof DField) {
                continue;
            }
            // type TS/DB pour frontend (heuristique inchangée)
            $dbType = $this->normalizeDbType(
                $field->customizedType ?: ($field->type->value ?? 'string')
            );

            $o = new \stdClass;
            $o->Field = $field->name;
            $o->Type = $dbType;
            // nullable si defaultValue == null ou champs techniques connus
            $forceNullableByName = in_array(Str::lower($field->name), ['id', 'uuid', 'hash'], true);
            $isNullish = $field->defaultValue === null || (is_string($field->defaultValue) && strtoupper(trim($field->defaultValue)) === 'NULL');
            $o->Null = ($forceNullableByName || $isNullish) ? 'YES' : 'NO';

            $columns[] = $o;
        }

        // Relations: DFModel->relations() est un tableau libre (type, foreignKey, table, model{...}, ...)
        $rels = $model->relations() ?? [];
        $belongsTo = [];
        $hasMany = [];

        foreach ($rels as $r) {
            $type = $r['type'] ?? '';
            if ($type === 'belongsTo') {
                $belongsTo[] = [
                    'model' => $r['model']['name'] ?? $this->tableNameToModelName((string) ($r['table'] ?? '')),
                    'field' => (string) ($r['foreignKey'] ?? 'foreign_id'),
                    'table' => $r['table'] ?? null,
                ];
            } elseif ($type === 'hasMany') {
                $hasMany[] = [
                    'model' => $r['model']['name'] ?? $this->tableNameToModelName((string) ($r['table'] ?? '')),
                    'field' => (string) ($r['foreignKey'] ?? ($this->tableName.'_id')),
                    'table' => $r['table'] ?? null,
                ];
            }
        }

        return [$columns, ['belongsTo' => $belongsTo, 'hasMany' => $hasMany]];
    }

    private function normalizeDbType(string $type): string
    {
        $t = Str::lower($type);

        if (Str::startsWith($t, 'enum(')) {
            return $type; // enum intact
        }

        return match (true) {
            $t === 'boolean', $t === 'bool', $t === 'tinyint(1)' => 'tinyint(1)',
            str_contains($t, 'bigint') => 'bigint',
            str_contains($t, 'float') => 'float',
            str_contains($t, 'double') => 'double',
            str_contains($t, 'decimal') => 'decimal',
            str_contains($t, 'text') => 'text',
            str_contains($t, 'json') => 'json',
            str_contains($t, 'timestamp') => 'timestamp',
            str_contains($t, 'date') => 'date',
            str_contains($t, 'int') => 'int',
            default => 'varchar(255)',
        };
    }

    /* ---------------------------------------------------------------------
     |  i18n (piloté par DFModel)
     * --------------------------------------------------------------------*/

    public function generateLangFileByAIFromDefinition(array $columns, bool $preview = false): bool
    {
        if (! $columns) {
            $this->consoleWriteError('Aucune colonne détectée.');

            return false;
        }

        $fieldKeys = array_map(fn ($c) => $c->Field, $columns);
        $generator = new LanguageGenerator;

        $translations = $generator->generateBilingualJson(
            entity: $this->modelName,
            module: $this->moduleName,
            fields: $fieldKeys,
            asArray: true
        );

        if ($preview) {
            $this->consoleWriteComment(json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return true;
        }

        foreach ($translations as $lang => $payload) {
            $entityKey = Str::camel($this->modelName);
            $basePath = $this->module->getPath('resources/ts/locales');
            $filePath = "{$basePath}/{$lang}.json";

            File::ensureDirectoryExists($basePath);

            $existing = File::exists($filePath)
                ? $this->safeJsonDecode(File::get($filePath))
                : [];

            $existing[$entityKey] = $payload;

            File::put($filePath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
        }

        $this->consoleWriteSuccess('Fichiers de langue générés (JSON).');

        return true;
    }

    /* ---------------------------------------------------------------------
     |  Menu
     * --------------------------------------------------------------------*/

    private function updateMenuItems(string $action): void
    {
        $filePath = $this->module->getPath('resources/ts/menuItems.json');

        if (! File::exists($filePath)) {
            File::put($filePath, "[\n]");
        }

        $menuItems = $this->safeJsonDecode(File::get($filePath));

        $pluralName = method_exists(Str::class, 'smartPlural')
            ? Str::smartPlural($this->modelName)
            : Str::plural($this->modelName);

        $routeName = Str::lower($this->moduleName.'-'.$pluralName);
        $title = Str::ucfirst($this->modelName);
        $titleKey = $this->moduleName.'.'.Str::camel($title).'.menuTitle';

        $newItem = [
            'title' => $titleKey,
            'to' => ['name' => $routeName],
            'icon' => ['icon' => 'bx-file-blank'],
            'action' => 'access',
            'subject' => $this->tableName,
        ];

        foreach ($menuItems as $item) {
            if (($item['title'] ?? null) === $newItem['title']) {
                $this->consoleWriteError("L'élément de menu '{$title}' existe déjà.");

                return;
            }
        }

        $menuItems[] = $newItem;
        File::put($filePath, json_encode($menuItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /* ---------------------------------------------------------------------
     |  Génération Vue / TS
     * --------------------------------------------------------------------*/

    public function generateVuePageFile(array $columns): void
    {
        $pluralName = method_exists(Str::class, 'smartPlural')
            ? Str::smartPlural($this->modelName)
            : Str::plural($this->modelName);

        $pageDir = Str::lower($pluralName);
        $filePath = $this->module->getPath("resources/ts/pages/{$pageDir}/index.vue");

        $stubPath = base_path('stubs/entity-generator/frontend/index.vue.stub');
        if (! File::exists($stubPath)) {
            throw new \RuntimeException('Le stub index.vue.stub est introuvable.');
        }

        $customDisplayTemplate = '';
        $headers = [];

        foreach ($columns as $col) {
            $field = $col->Field;
            $type = $col->Type;

            if ($type === 'tinyint(1)') {
                $customDisplayTemplate .= $this->buildBooleanDisplayTemplate($field);
            }

            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by_id', 'updated_by_id', 'deleted_by_id'], true)) {
                continue;
            }

            $headers[] = "{ title: '".Str::ucfirst($field)."', key: '{$field}' }";
        }

        $headersString = $headers ? implode(",\n  ", $headers).',' : '';

        $vueCode = str_replace(
            ['{{ modelName }}', '{{ headers }}', '{{ moduleName }}', '{{ permissionsSubject }}', '{{ customDisplayColumn }}', '{{ moduleName }}'],
            [$this->modelName, $headersString, $this->moduleName, $this->tableName, $customDisplayTemplate, $this->modelName],
            File::get($stubPath)
        );

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $vueCode);
    }

    public function generateAddOrEditComponent(array $columns, array $relations): void
    {
        $filePath = $this->module->getPath("resources/ts/components/{$this->moduleName}{$this->modelName}AddOrEdit.vue");

        $stubPath = base_path('stubs/entity-generator/frontend/addOrEdit.vue.stub');
        if (! File::exists($stubPath)) {
            throw new \RuntimeException('Le stub addOrEdit.vue.stub est introuvable.');
        }

        $fields = [];
        $defaults = [];
        $relationLists = [];
        $loadRelationLists = [];
        $imports = [];
        $entityKey = Str::camel($this->modelName);

        $pickItemTitle = function (array $relatedCols): string {
            $preferred = ['name', 'title', 'code'];
            foreach ($preferred as $p) {
                if (in_array($p, $relatedCols, true)) {
                    return $p;
                }
            }
            foreach ($relatedCols as $i => $c) {
                if ($c === 'id' && isset($relatedCols[$i + 1])) {
                    $candidate = $relatedCols[$i + 1];
                    if (! Str::endsWith($candidate, '_id')) {
                        return $candidate;
                    }
                }
            }

            return 'id';
        };

        // index rapide des belongsTo par field
        $btByField = [];
        foreach (($relations['belongsTo'] ?? []) as $b) {
            $btByField[$b['field']] = $b;
        }

        foreach ($columns as $col) {
            $fieldName = $col->Field;
            $labelKey = Str::camel($fieldName);
            $dbType = $col->Type;

            if (isset($btByField[$fieldName])) {
                $b = $btByField[$fieldName];
                $relModel = $b['model'];
                $listVar = Str::camel($relModel).'List';

                $itemTitleField = $pickItemTitle(['id', 'name', 'title', 'code']);

                $modulePrefix = Str::lower(strtok((string) ($b['table'] ?? ''), '_') ?: $this->moduleNameLower);
                $sameModule = $modulePrefix === $this->moduleNameLower;
                $alias = $sameModule ? $this->moduleNameLower : $modulePrefix;

                $imports[] = "import { {$relModel}API } from '@{$alias}/api/{$relModel}';";
                $imports[] = "import type { I{$relModel} } from '@{$alias}/types/entities';";

                $relationLists[] = "const {$listVar} = ref<I{$relModel}[]>([]);";
                $loadRelationLists[] = "{$listVar}.value = await {$relModel}API.getAll();";

                $fields[] = <<<HTML
                    <CoreAutocomplete
                        v-model="form.{$fieldName}"
                        :label="t('{$this->moduleName}.{$entityKey}.field.{$labelKey}')"
                        :items="{$listVar}"
                        item-value="id"
                        item-title="{$itemTitleField}"
                        required
                        :readonly="readonly"
                    />
                HTML;
            } elseif (Str::startsWith($dbType, 'enum')) {
                $enumItems = implode(',', array_map(fn ($v) => "'{$v}'", $this->parseEnumValues($dbType)));
                $fields[] = <<<HTML
                    <CoreAutocomplete
                        v-model="form.{$fieldName}"
                        :label="t('{$this->moduleName}.{$entityKey}.field.{$labelKey}')"
                        :items=[{$enumItems}]
                        required
                        :readonly="readonly"
                    />
                HTML;
            } else {
                if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at', 'uuid'], true)) {
                    continue;
                }
                $component = $this->getVuetifyComponent($dbType);
                $fields[] = <<<HTML
                    <{$component}
                        v-model="form.{$fieldName}"
                        :label="t('{$this->moduleName}.{$entityKey}.field.{$labelKey}')"
                        required
                        :error-messages="errorMessage.{$fieldName}"
                        :readonly="readonly"
                    />
                HTML;
            }

            $defaults[] = "{$fieldName}: ".$this->getDefaultValue($dbType);
        }

        $replacements = [
            '{{ modelName }}' => $this->modelName,
            '{{ formFields }}' => implode("\n          ", $fields),
            '{{ defaultValues }}' => '{ '.implode(', ', $defaults).' }',
            '{{ relationLists }}' => implode("\n", $relationLists),
            '{{ loadRelationLists }}' => implode("\n  ", $loadRelationLists),
            '{{ imports }}' => implode("\n", array_values(array_unique($imports, SORT_STRING))),
            '{{ moduleName }}' => Str::ucfirst($this->moduleName),
            '{{ modelNameCamelCase }}' => Str::camel($this->modelName),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), File::get($stubPath));

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $content);
    }

    private function generateApiServiceFile(): void
    {
        $filePath = "{$this->apiDirectory}{$this->modelName}.ts";

        $stubPath = base_path('stubs/entity-generator/frontend/api.stub');
        if (! File::exists($stubPath)) {
            throw new \RuntimeException('Le stub api.stub est introuvable.');
        }

        $serviceCode = str_replace(
            ['{{ modelName }}', '{{ tableName }}', '{{ baseUrl }}'],
            [$this->modelName, $this->tableName, $this->baseUrl],
            File::get($stubPath)
        );

        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $serviceCode);
    }

    /* ---------------------------------------------------------------------
     |  Mapping types -> TS / Vuetify
     * --------------------------------------------------------------------*/

    /** @param array<int,object> $columns */
    private function mapColumnsToTypeScript(array $columns, array $relations): string
    {
        $lines = [];

        foreach ($columns as $c) {
            $field = $c->Field;
            $dbType = $c->Type;
            $isSqlNullable = strtoupper((string) $c->Null) === 'YES';
            $forceNullable = in_array(Str::lower($field), ['hash', 'uuid'], true);
            $isNullable = $isSqlNullable || $forceNullable;
            $tsType = $this->toTypeScriptType($dbType, $isNullable);
            $opt = $isNullable ? '?' : '';
            $lines[] = "  {$field}{$opt}: {$tsType};";
        }

        foreach (($relations['hasMany'] ?? []) as $rel) {
            $model = $rel['model'];
            $lines[] = "  {$model}?: I{$model}[];";
        }

        foreach (($relations['belongsTo'] ?? []) as $rel) {
            $model = $rel['model'];
            $lines[] = "  {$model}?: I{$model};";
        }

        $body = implode("\n", $lines);

        return <<<TS
        export interface I{$this->modelName} {
            {$body}
        }
        TS;
    }

    private function toTypeScriptType(string $dbType, bool $nullable = false): string
    {
        $t = Str::lower($dbType);

        if (Str::startsWith($t, 'enum(')) {
            $type = 'string';
        } else {
            $type = match (true) {
                str_contains($t, 'int') => 'number',
                str_contains($t, 'float') => 'number',
                str_contains($t, 'double') => 'number',
                str_contains($t, 'decimal') => 'number',
                str_contains($t, 'json') => 'any',
                str_contains($t, 'date') => 'any',
                str_contains($t, 'time') => 'string',
                str_contains($t, 'text') => 'string',
                str_contains($t, 'char') => 'string',
                default => 'string',
            };
        }

        return $nullable ? "{$type} | null" : $type;
    }

    private function getDefaultValue(string $dbType): ?string
    {
        $t = Str::lower($dbType);
        if (Str::startsWith($t, 'enum(')) {
            return "''";
        }

        return match (true) {
            str_contains($t, 'bigint') => 'null',
            str_contains($t, 'int') => '0',
            str_contains($t, 'float') => '0.0',
            str_contains($t, 'double') => '0.0',
            str_contains($t, 'decimal') => '0.0',
            str_contains($t, 'char') => "''",
            str_contains($t, 'text') => "''",
            str_contains($t, 'date') => "''",
            str_contains($t, 'time') => "''",
            str_contains($t, 'json') => "'{}'",
            default => "''",
        };
    }

    private function getVuetifyComponent(string $dbType): string
    {
        $t = Str::lower($dbType);
        if ($t === 'tinyint(1)') {
            return 'VCheckbox';
        }

        return match (true) {
            str_contains($t, 'int') => 'CoreTextField type="number"',
            str_contains($t, 'float') => 'CoreTextField type="number"',
            str_contains($t, 'double') => 'CoreTextField type="number"',
            str_contains($t, 'decimal') => 'CoreTextField type="number"',
            str_contains($t, 'text') => 'CoreTextarea',
            str_contains($t, 'json') => 'CoreTextarea',
            str_contains($t, 'date') => 'CoreDate',
            str_contains($t, 'timestamp') => 'CoreDate',
            str_contains($t, 'datetime') => 'CoreDate',
            str_contains($t, 'time') => 'CoreDate',
            default => 'CoreTextField',
        };
    }

    /* ---------------------------------------------------------------------
     |  helpers
     * --------------------------------------------------------------------*/

    private function buildBooleanDisplayTemplate(string $field): string
    {
        return <<<HTML
            <template #item.{$field}="{ item }">
                <VChip :color="item.{$field} ? 'success' : 'error'" size="small">
                    {{ item.{$field} ? t('action.yes') : t('action.no') }}
                </VChip>
            </template>

        HTML;
    }

    private function parseEnumValues(string $enumDefinition): array
    {
        if (preg_match("/^enum\((.*)\)$/i", $enumDefinition, $m)) {
            $inside = $m[1];

            return array_map(fn ($v) => trim($v, " '\""), explode(',', $inside));
        }

        return [];
    }

    private function tableNameToModelName(string $tableName): string
    {
        return Str::studly(Str::singular($tableName));
    }

    /** @return array<mixed> */
    private function safeJsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON invalide : '.json_last_error_msg());
        }

        return $data;
    }

    private function updateTypeDefinitionFile(string $newInterface): void
    {
        if (! File::exists($this->typesFilePath)) {
            File::put($this->typesFilePath, trim($newInterface).PHP_EOL);

            return;
        }

        $content = File::get($this->typesFilePath);
        $pattern = "/export interface I{$this->modelName} \{.*?\}/s";

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newInterface, $content);
        } else {
            $content .= PHP_EOL.PHP_EOL.$newInterface;
        }

        File::put($this->typesFilePath, trim($content).PHP_EOL);
    }
}
