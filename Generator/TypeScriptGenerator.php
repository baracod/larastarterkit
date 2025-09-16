<?php

namespace App\Generator;

use App\Generator\IA\LanguageGenerator;
use App\Generator\Utils\ConsoleTrait;
use App\Generator\Utils\GeneratorTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\spin;

/**
 * Générateur de fichiers TypeScript + vues + services + i18n
 * à partir d'une table SQL et d'un modèle métier.
 *
 * - Crée/Met à jour :
 *   - interface TS (entities.d.ts)
 *   - service API TS (api/{Model}.ts)
 *   - page index.vue (pages/{models}/index.vue)
 *   - composant AddOrEdit.vue (components/{Module}{Model}AddOrEdit.vue)
 *   - fichiers de langues (resources/ts/locales/*.json)
 */
class TypeScriptGenerator
{
    use ConsoleTrait;
    use GeneratorTrait;

    private string $tableName;

    private string $modelName;

    private string $typesFilePath;

    private string $apiDirectory;

    private string $moduleName;

    private string $moduleNameLower;

    private string $baseUrl;

    private ModuleGenerator $module;

    /**
     * @param  string  $tableName  Nom de la table SQL d'origine (ex. base_users)
     * @param  string  $modelName  Nom du modèle (ex. User)
     * @param  string  $moduleName  Nom du module Nwidart (ex. Base)
     */
    public function __construct(string $tableName, string $modelName, string $moduleName)
    {
        $this->tableName = $tableName;
        $this->modelName = $modelName;
        $this->moduleName = $moduleName;
        $this->module = new ModuleGenerator($moduleName);
        $this->typesFilePath = $this->module->getPath('resources/ts/types/entities.d.ts');
        $this->apiDirectory = $this->module->getPath('resources/ts/api/');
        $this->baseUrl = Str::lower($moduleName.'/'.Str::smartPlural($modelName));
        $this->moduleNameLower = Str::lower($moduleName);
    }

    /**
     * Lance la génération/maj des artefacts TypeScript, Vue et i18n pour l’entité.
     *
     * @return bool true si tout s’est bien passé
     *
     * @throws \RuntimeException Si la table n'existe pas
     */
    public function generate(): bool
    {
        if (! DB::getSchemaBuilder()->hasTable($this->tableName)) {
            throw new \RuntimeException("La table `{$this->tableName}` n'existe pas.");
        }

        $columns = $this->extractColumns();
        $relations = $this->detectRelations();
        $typeInterface = $this->mapColumnsToTypeScript($columns, $relations);

        $this->generateAddOrEditComponent();
        $this->updateTypeDefinitionFile($typeInterface);
        $this->generateApiServiceFile();
        $this->generateVuePageFile();
        $this->updateMenuItems('add');
        $this->generateLangFileByAI();

        return true;
    }

    /**
     * Génère/met à jour les fichiers de langues <module>/resources/ts/locales/{lang}.json
     * à partir des colonnes de la table et d’un générateur IA.
     *
     * @param  bool  $preview  Si true, affiche le JSON généré sans écrire sur disque
     */
    public function generateLangFileByAI(bool $preview = false): bool
    {
        $columns = $this->extractColumns();
        if (! $columns) {
            $this->consoleWriteError('Aucune colonne détectée.');

            return false;
        }

        $fieldKeys = array_map(fn ($c) => $c->Field, $columns);
        $generator = new LanguageGenerator;

        $translations = spin(
            message: 'Génération des fichiers de langues…',
            callback: fn () => $generator->generateBilingualJson(
                entity: $this->modelName,
                module: $this->moduleName,
                fields: $fieldKeys,
                asArray: true
            )
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

        $this->consoleWriteSuccess('Fichiers de langue générés.');

        return true;
    }

    /**
     * Met à jour le fichier menuItems.json pour ajouter l’entrée liée à l’entité.
     *
     * @param  string  $action  'add'|'update'|'delete' (actuellement gère 'add')
     */
    private function updateMenuItems(string $action): void
    {
        $filePath = $this->module->getPath('resources/ts/menuItems.json');

        if (! File::exists($filePath)) {
            File::put($filePath, "[\n]");
        }

        $menuItems = $this->safeJsonDecode(File::get($filePath));
        $routeName = Str::lower($this->moduleName.'-'.Str::smartPlural($this->modelName));
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

    /**
     * Génère la page liste (index.vue) avec entêtes de table et colonnes custom booléennes.
     *
     * @throws \RuntimeException Si le stub est introuvable
     */
    public function generateVuePageFile(): void
    {
        $pageDir = Str::lower(Str::smartPlural($this->modelName));
        $filePath = $this->module->getPath("resources/ts/pages/{$pageDir}/index.vue");

        $stubPath = base_path('stubs/entity-generator/frontend/index.vue.stub');
        if (! File::exists($stubPath)) {
            throw new \RuntimeException('Le stub index.vue.stub est introuvable.');
        }

        $columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
        $customDisplayTemplate = '';
        $headers = [];

        foreach ($columns as $col) {
            if ($col->Type === 'tinyint(1)') {
                $customDisplayTemplate .= $this->buildBooleanDisplayTemplate($col->Field);
            }

            if (in_array($col->Field, ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by_id', 'updated_by_id', 'deleted_by_id'], true)) {
                continue;
            }

            $headers[] = "{ title: '".Str::ucfirst($col->Field)."', key: '{$col->Field}' }";
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

    /**
     * Génère le composant de formulaire AddOrEdit.vue, avec gestion automatique
     * des champs belongsTo (listes) et des ENUM MySQL.
     *
     * @throws \RuntimeException Si le stub est introuvable
     */
    public function generateAddOrEditComponent(): void
    {
        $filePath = $this->module->getPath("resources/ts/components/{$this->moduleName}{$this->modelName}AddOrEdit.vue");

        $stubPath = base_path('stubs/entity-generator/frontend/addOrEdit.vue.stub');
        if (! File::exists($stubPath)) {
            throw new \RuntimeException('Le stub addOrEdit.vue.stub est introuvable.');
        }

        $columns = DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
        $relations = $this->detectRelations();

        $fields = [];
        $defaults = [];
        $relationLists = [];
        $loadRelationLists = [];
        $imports = [];
        $entityKey = Str::camel($this->modelName);

        $pickItemTitle = function (array $relatedCols): string {
            $preferred = ['name', 'title', 'code'];
            $names = array_map(fn ($c) => $c->Field, $relatedCols);
            foreach ($preferred as $p) {
                if (in_array($p, $names, true)) {
                    return $p;
                }
            }
            foreach ($relatedCols as $i => $c) {
                if ($c->Field === 'id' && isset($relatedCols[$i + 1])) {
                    $candidate = $relatedCols[$i + 1]->Field;
                    if (! Str::endsWith($candidate, '_id')) {
                        return $candidate;
                    }
                }
            }

            return 'id';
        };

        foreach ($columns as $col) {
            $fieldName = $col->Field;
            $labelKey = Str::camel($fieldName);

            $belongsTo = collect($relations['belongsTo'] ?? [])->firstWhere('field', $fieldName);

            if ($belongsTo) {
                $relatedTable = $belongsTo['table'];
                $relatedModel = $belongsTo['model'];
                $relatedCols = DB::select("SHOW COLUMNS FROM `{$relatedTable}`");
                $itemTitleField = $pickItemTitle($relatedCols);
                $listVar = Str::camel($relatedModel).'List';

                $modulePrefix = Str::lower(strtok($relatedTable, '_') ?: '');
                $sameModule = $modulePrefix === $this->moduleNameLower;
                $alias = $sameModule ? $this->moduleNameLower : $modulePrefix;

                $imports[] = "import { {$relatedModel}API } from '@{$alias}/api/{$relatedModel}';";
                $imports[] = "import type { I{$relatedModel} } from '@{$alias}/types/entities';";

                $relationLists[] = "const {$listVar} = ref<I{$relatedModel}[]>([]);";
                $loadRelationLists[] = "{$listVar}.value = await {$relatedModel}API.getAll();";

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
            } elseif (Str::startsWith($col->Type, 'enum')) {
                $enumItems = implode(',', array_map(fn ($item) => "'$item'", $this->parseEnumValues($col->Type)));
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
                $component = $this->getVuetifyComponent($col->Type);
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

            $defaults[] = "{$fieldName}: ".$this->getDefaultValue($col->Type);
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

    /**
     * Extrait les valeurs d’un type ENUM MySQL.
     *
     * @param  string  $enumDefinition  Exemple: "enum('A','B','C')"
     * @return array<string>
     */
    private function parseEnumValues(string $enumDefinition): array
    {
        if (preg_match("/^enum\((.*)\)$/i", $enumDefinition, $m)) {
            $inside = $m[1];

            return array_map(fn ($v) => trim($v, " '\""), explode(',', $inside));
        }

        return [];
    }

    /**
     * Donne une valeur par défaut TS (string/number/json/null) pour un type SQL.
     *
     * @param  string  $dbType  Type SQL (SHOW COLUMNS -> Type)
     */
    private function getDefaultValue(string $dbType): ?string
    {
        return match (true) {
            str_contains($dbType, 'bigint') => 'null',
            str_contains($dbType, 'int') => '0',
            str_contains($dbType, 'float') => '0.0',
            str_contains($dbType, 'double') => '0.0',
            str_contains($dbType, 'decimal') => '0.0',
            str_contains($dbType, 'char') => "''",
            str_contains($dbType, 'text') => "''",
            str_contains($dbType, 'date') => "''",
            str_contains($dbType, 'time') => "''",
            str_contains($dbType, 'json') => "'{}'",
            default => "''",
        };
    }

    /**
     * Retourne le composant Vuetify à utiliser selon le type SQL.
     *
     * @param  string  $dbType  Type SQL (SHOW COLUMNS -> Type)
     * @return string Nom du composant + props éventuelles
     */
    private function getVuetifyComponent(string $dbType): string
    {
        return match (true) {
            str_contains($dbType, 'tinyint(1)') => 'VCheckbox',
            str_contains($dbType, 'int') => 'CoreTextField type="number"',
            str_contains($dbType, 'float') => 'CoreTextField type="number"',
            str_contains($dbType, 'double') => 'CoreTextField type="number"',
            str_contains($dbType, 'decimal') => 'CoreTextField type="number"',
            str_contains($dbType, 'char') => 'CoreTextField',
            str_contains($dbType, 'text') => 'CoreTextarea',
            str_contains($dbType, 'date') => 'VDateInput',
            str_contains($dbType, 'timestamp') => 'VDateInput',
            str_contains($dbType, 'time') => 'VDateInput',
            str_contains($dbType, 'json') => 'VTextarea',
            default => 'VTextField',
        };
    }

    /**
     * Génère le service API TypeScript (resources/ts/api/{Model}.ts) à partir d’un stub.
     *
     * @throws \RuntimeException Si le stub est introuvable
     */
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

    /**
     * Construit l’interface TS I{Model} à partir des colonnes SQL et des relations.
     *
     * @param  array  $columns  Résultat SHOW COLUMNS (array<stdClass>)
     * @param  array  $relations  ['hasMany'=>[], 'belongsTo'=>[]]
     * @return string Code TypeScript export interface I{Model}
     */
    private function mapColumnsToTypeScript(array $columns, array $relations): string
    {
        $lines = [];

        foreach ($columns as $col) {
            $fieldName = $col->Field;
            $dbType = $col->Type;
            $isSqlNullable = strtoupper((string) $col->Null) === 'YES';
            $forceNullable = in_array(Str::lower($fieldName), ['hash', 'uuid'], true);
            $isNullable = $isSqlNullable || $forceNullable;
            $tsType = $this->toTypeScriptType($dbType, $isNullable);
            $optionalSuffix = $isNullable ? '?' : '';
            $lines[] = "  {$fieldName}{$optionalSuffix}: {$tsType};";
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

    /**
     * Traduit un type SQL en type TypeScript (number/string/any), avec nullabilité.
     *
     * @param  string  $dbType  Type SQL (SHOW COLUMNS -> Type)
     * @param  bool  $nullable  Si true, ajoute "| null"
     */
    private function toTypeScriptType(string $dbType, bool $nullable = false): string
    {
        $type = match (true) {
            str_contains($dbType, 'int') => 'number',
            str_contains($dbType, 'float') => 'number',
            str_contains($dbType, 'double') => 'number',
            str_contains($dbType, 'decimal') => 'number',
            str_contains($dbType, 'char') => 'string',
            str_contains($dbType, 'text') => 'string',
            str_contains($dbType, 'date') => 'string',
            str_contains($dbType, 'time') => 'string',
            str_contains($dbType, 'json') => 'any',
            default => 'string',
        };

        return $nullable ? "{$type} | null" : $type;
    }

    /**
     * Ajoute/remplace l’interface I{Model} dans entities.d.ts.
     *
     * @param  string  $newInterface  Code de l’interface
     */
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

    /**
     * Retourne la description des colonnes via SHOW COLUMNS FROM table.
     *
     * @return array<int,object> Chaque élément possède Field/Type/Null/etc.
     */
    private function extractColumns(): array
    {
        return DB::select("SHOW COLUMNS FROM `{$this->tableName}`");
    }

    /**
     * Détecte les relations belongsTo/hasMany via information_schema.
     *
     * @return array{belongsTo:array<int,array{model:string,field:string,table?:string}>, hasMany:array<int,array{model:string,field:string}>}
     */
    private function detectRelations(): array
    {
        $database = $this->databaseName();

        $belongsToRows = DB::select(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $this->tableName]
        );

        $hasManyRows = DB::select(
            'SELECT TABLE_NAME, COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?',
            [$database, $this->tableName]
        );

        $relations = ['belongsTo' => [], 'hasMany' => []];

        foreach ($belongsToRows as $row) {
            $relations['belongsTo'][] = [
                'model' => $this->tableNameToModelName($row->REFERENCED_TABLE_NAME),
                'field' => $row->COLUMN_NAME,
                'table' => $row->REFERENCED_TABLE_NAME,
            ];
        }

        foreach ($hasManyRows as $row) {
            $relations['hasMany'][] = [
                'model' => $this->tableNameToModelName($row->TABLE_NAME),
                'field' => $row->COLUMN_NAME,
            ];
        }

        return $relations;
    }

    /**
     * Template Vue pour l’affichage des booléens sous forme de chip.
     *
     * @param  string  $field  Nom du champ
     * @return string Template de slot VDataTable
     */
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

    /**
     * JSON decode avec gestion d’erreur (exception si invalide).
     *
     * @param  string  $json  Chaîne JSON
     * @return array<mixed>
     *
     * @throws \RuntimeException Si le JSON est invalide
     */
    private function safeJsonDecode(string $json): array
    {
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON invalide : '.json_last_error_msg());
        }

        return $data;
    }

    /**
     * Récupère le nom de la base active (config/database).
     */
    private function databaseName(): string
    {
        return config('database.connections.'.config('database.default').'.database')
            ?? (string) env('DB_DATABASE');
    }

    /**
     * Convertit un nom de table SQL en nom de modèle StudlyCase singulier.
     * Ex. "auth_users" => "AuthUser", "posts" => "Post".
     */
    private function tableNameToModelName(string $tableName): string
    {
        return Str::studly(Str::singular($tableName));
    }
}
