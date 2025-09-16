<?php

namespace App\Generator;

use App\Generator\Utils\ConsoleTrait;
use App\Generator\Utils\GeneratorTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use phpDocumentor\Reflection\Types\This;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;

class ModelGen
{
    use ConsoleTrait, GeneratorTrait;

    private const MAX_RECURSION_DEPTH = 2;

    private static array $generated  = [];
    private static array $inProgress = [];

    public string $tableName;
    public string $modelName;
    public ?string $moduleName;
    private string $namespace;
    private ?ModuleGenerator $moduleGenerator = null;
    private int $recursionDepth = 0;

    public function __construct(string $modelName, string $tableName, ?string $moduleName = null)
    {
        $this->modelName  = $modelName;
        $this->tableName  = $tableName;
        $this->moduleName = $moduleName;
        $this->setNamespaceAndModule();
        $this->writeData();
    }

    private function writeData(): void
    {
        $fillable = array_filter(
            Schema::getColumnListing($this->tableName),
            fn($column) => !in_array($column, ['id', 'created_at', 'updated_at'])
        );


        // $fillable = Schema::getColumns($this->tableName);
        $fillable = multiselect(
            "Sélectionner les champs fillables, si rien tous seront fillables",
            $fillable,
            $fillable,
            15
        );

        $belongsTo   = $this->getDataBelongsToRelations();
        $hasMany     = $this->detectHasManyRelations();
        $manyToMany  = $this->detectBelongsToManyRelations();

        dd($belongsTo);
        $relations = [];

        foreach ($belongsTo as $ClassName => $belongTo) {
            $relations[$ClassName] = $belongTo;
        }






        // "models" => array:1 [
        // "BaseSite" => array:2 [
        //   "table" => "base_sites"
        //   "module" => "base"
        // ]


        // dd($belongsTo,  $hasMany, $manyToMany);
        dd($belongsTo);

        dd($fillable);
        $cacheData = [
            "modelName" => Str::camel($this->modelName),
            "table" => $this->tableName,
            "module" => Str::pascal($this->moduleName),
            "key" => Str::slug($this->modelName),
            "className" => Str::pascal($this->modelName),
            "fillable" => $fillable,
            "relations" => $relations,
            "casts" => [],
            "hasFrontend" => false,
            "permissions" => []
        ];
    }

    private function getDataBelongsToRelations(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database')
            ?? env('DB_DATABASE');

        $rows = DB::select(
            "SELECT
            kcu.COLUMN_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = ?
            AND kcu.TABLE_NAME = ?
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL",
            [$database, $this->tableName]
        );

        $relations = [];     // liste d'items normalisés
        $models    = [];     // pour déclencher la génération des modèles liés (si tu l'utilises ailleurs)

        foreach ($rows as $r) {
            $foreignKey  = $r->COLUMN_NAME;                       // ex: province_id
            $table       = $r->REFERENCED_TABLE_NAME;             // ex: base_provinces
            $ownerKey    = $r->REFERENCED_COLUMN_NAME ?? 'id';    // clé du modèle RELIÉ (3e param Eloquent)
            $modelName   = Str::studly(Str::singular($table));    // ex: BaseProvince -> BaseProvince / Province selon ta convention
            $methodName  = Str::camel(Str::beforeLast($foreignKey, '_id')) ?: Str::camel($modelName);
            $namespace   = "{$this->namespace}\\{$modelName}";
            $moduleName  = Str::pascal(explode('_', $table)[0] ?? '');
            // Item métier demandé
            $relations[$modelName] = [
                'type'       => 'belongsTo',
                'foreignKey' => $foreignKey,      // 2e param Eloquent
                'model'      => [
                    'name'      => $modelName,
                    'namespace' => $namespace,
                ],
                'table'      => $table,           // redondant mais conservé si tes consumers l’attendent
                'ownerKey'   => $ownerKey,        // 3e param Eloquent (clé sur le modèle lié)
                'name'       => $methodName,      // nom de la méthode à générer dans le modèle
                'moduleName' =>  $moduleName,
                'externalModule' => $this->moduleName != $moduleName
            ];

            // Pour une éventuelle génération automatique des modèles liés

        }

        return $relations;
    }


    public function setRecursionDepth(int $depth): self
    {
        $this->recursionDepth = $depth;
        return $this;
    }

    public function setModelName(string $modelName)
    {
        $this->modelName = $modelName;
    }

    public function generate(?array &$response = null): bool
    {
        $fqcn = $this->namespace . '\\' . $this->modelName;

        if (isset(self::$generated[$fqcn]) || isset(self::$inProgress[$fqcn])) {
            $this->consoleWriteMessage("Skip `{$fqcn}` (déjà vu)");
            return true;
        }
        self::$inProgress[$fqcn] = true;

        try {
            $this->consoleWriteMessage("Génération du modèle `{$this->modelName}`...");

            if (!$this->tableExists()) {
                abort(500, "La table `{$this->tableName}` n'existe pas.");
            }

            $this->ensureModuleExists();

            $fillable      = $this->getFillableColumns();
            $relationsData = $this->detectRelations();
            $filePath      = $this->getModelFilePath();

            $allowOverwrite = true;
            if (File::exists($filePath)) {
                $allowOverwrite = confirm("Voulez-vous écraser le modèle `{$this->modelName}` ?");
            }
            if (!$allowOverwrite) {
                self::$generated[$fqcn] = true;
                unset(self::$inProgress[$fqcn]);
                return false;
            }

            $content = $this->generateModelContent($fillable, $relationsData);
            $this->writeModelFile($filePath, $content);

            self::$generated[$fqcn] = true;
            unset(self::$inProgress[$fqcn]);

            if ($this->recursionDepth < self::MAX_RECURSION_DEPTH) {
                $this->generateRelatedModels($relationsData['models']);
            }

            $response = [
                'message' => "Le modèle `{$this->modelName}` a été généré avec succès.",
                'path'    => $filePath,
                'status'  => 200,
            ];
            $this->generatePermissions();
            $this->consoleWriteSuccess($response['message']);
            return true;
        } catch (\Throwable $e) {
            unset(self::$inProgress[$fqcn]);
            $this->consoleWriteError("<error>{$e->getMessage()}</error>");
            return false;
        }
    }

    public function generatePermissions(): void
    {
        $this->consoleWriteMessage("🔧 Génération des permissions pour `{$this->modelName}`...");

        $pluralModel = Str::smartPlural($this->modelName);
        $actions = [
            'add'    => "Ajouter un(e) {$this->modelName}",
            'edit'   => "Modifier un(e) {$this->modelName}",
            'delete' => "Supprimer un(e) {$this->modelName}",
            'browse' => "Parcourir les {$pluralModel}",
            'access' => "Accéder aux {$pluralModel}",
        ];

        $adminRole = DB::table('auth_roles')->where('name', 'administrator')->first();

        foreach ($actions as $action => $label) {
            $permissionKey = "{$action}_{$this->tableName}";

            if (DB::table('auth_permissions')->where('key', $permissionKey)->exists()) {
                $this->consoleWriteMessage("🔁 Permission `{$permissionKey}` déjà existante.");
                continue;
            }

            $permissionId = DB::table('auth_permissions')->insertGetId([
                'description' => $label,
                'table_name'  => $this->tableName,
                'action'      => $action,
                'subject'     => $this->tableName,
                'key'         => $permissionKey,
            ]);

            if ($adminRole) {
                DB::table('auth_role_permissions')->insert([
                    'role_id'       => $adminRole->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->consoleWriteSuccess("✅ Permissions générées pour `{$this->modelName}`.");
    }

    private function setNamespaceAndModule(): void
    {
        if ($this->moduleName !== null) {
            $this->namespace = "Modules\\{$this->moduleName}\\Models";
            $this->moduleGenerator = new ModuleGenerator($this->moduleName);

            if (!Module::find($this->moduleName)) {
                abort(500, "Le module `{$this->moduleName}` n'existe pas.");
            }
        } else {
            $this->namespace = 'App\Models';
        }
    }

    private function tableExists(): bool
    {
        return Schema::hasTable($this->tableName);
    }

    private function ensureModuleExists(): void
    {
        if ($this->moduleName && $this->moduleGenerator && !$this->moduleGenerator->exists()) {
            $this->moduleGenerator->generate();
        }
    }

    private function getFillableColumns(): string
    {
        $columns = Schema::getColumnListing($this->tableName);
        $columns = array_diff($columns, ['id', 'created_at', 'updated_at']);
        return "'" . implode("', '", $columns) . "'";
    }

    private function generateModelContent(string $fillable, array $relationsData): string
    {
        $templatePath = base_path('stubs/entity-generator/Model.stub');
        $traitMeta    = $this->getOtherTraits();

        $filePath = $this->getModelFilePath();
        $existingContent = File::exists($filePath) ? File::get($filePath) : '';

        // 1) Relations existantes dans le fichier actuel
        $existingRelationMap = $this->extractRelationMethods($existingContent); // [method => code]

        // 2) Nouvelles relations proposées (filtrées: on ne garde que celles qui n'existent pas déjà)
        $newRelationMap = $relationsData['relationMap'] ?? [];
        foreach (array_keys($existingRelationMap) as $method) {
            unset($newRelationMap[$method]);
        }

        // 3) Fusion : on garde les existantes + on ajoute les nouvelles
        $finalRelationMap = $existingRelationMap + $newRelationMap;
        $relationsBlock   = $finalRelationMap ? '    ' . implode("\n\n    ", array_values($finalRelationMap)) : '';

        // Imports existants + nouveaux (relations + traits) dédupliqués
        $existingUses = $this->extractImportLines($existingContent);        // array de "use ...;"
        $newUses      = array_filter([
            trim($relationsData['imports'] ?? ''),
            trim($traitMeta['namespace'] ?? ''),
        ]);
        $importsBlock = trim(implode("\n", array_unique(array_merge($existingUses, $newUses))));

        $propsArray = $traitMeta['props'] ?? [];
        $propsBlock = $propsArray ? '    ' . implode("\n\n    ", $propsArray) : '';

        return str_replace(
            [
                '{{ namespace }}',
                '{{ modelName }}',
                '{{ tableName }}',
                '{{ fillable }}',
                '{{ relations }}',
                '{{ imports }}',
                '{{ traitNames }}',
                '{{ traitNamespaces }}',
                '{{ props }}',
            ],
            [
                $this->namespace,
                $this->modelName,
                $this->tableName,
                $fillable,
                $relationsBlock,
                $importsBlock,
                trim($traitMeta['traits'] ?? ''),
                '',
                $propsBlock,
            ],
            File::get($templatePath)
        );
    }

    private function getModelFilePath(): string
    {
        return $this->moduleName !== null
            ? Module::getModulePath($this->moduleName) . "app/Models/{$this->modelName}.php"
            : app_path("Models/{$this->modelName}.php");
    }

    private function writeModelFile(string $filePath, string $content): void
    {
        File::ensureDirectoryExists(dirname($filePath));
        File::put($filePath, $content);
    }

    private function generateRelatedModels(array $models): void
    {

        foreach ($models as $relatedModelName => $data) {

            $relatedModelName = text('Nom du modèle lié :', "Le modèle a le nom $relatedModelName par défaut") ?? $relatedModelName;
            $relatedNamespace = $this->moduleName !== null
                ? "Modules\\{$this->moduleName}\\Models"
                : 'App\Models';

            $relatedFqcn = $relatedNamespace . '\\' . $relatedModelName;

            if (isset(self::$generated[$relatedFqcn]) || isset(self::$inProgress[$relatedFqcn])) {
                $this->consoleWriteMessage("Skip lié `{$relatedFqcn}` (déjà vu)");
                continue;
            }

            if ($this->moduleGenerator && $this->moduleGenerator->modelExist($relatedModelName)) {
                info("Le modèle lié `{$relatedModelName}` existe déjà.", '');
                continue;
            }

            (new self($relatedModelName, $data['table'], $data['module'] ?? $this->moduleName))
                ->setRecursionDepth($this->recursionDepth + 1)
                ->generate();
        }
    }

    private function detectRelations(): array
    {
        $acc = ['relations' => [], 'imports' => [], 'models' => [], 'relationMap' => []];

        $belongsTo   = $this->detectBelongsToRelations();
        $hasMany     = $this->detectHasManyRelations();
        $manyToMany  = $this->detectBelongsToManyRelations();


        foreach ([$belongsTo, $hasMany, $manyToMany] as $chunk) {
            $acc['relations']   = array_merge($acc['relations'],   $chunk['relations']);
            $acc['imports']     = array_merge($acc['imports'],     $chunk['imports']);
            $acc['models']      = array_merge($acc['models'],      $chunk['models']);
            $acc['relationMap'] = array_merge($acc['relationMap'], $chunk['relationMap']); // [method => code]
        }

        return [
            'relations'   => implode("\n\n    ", array_unique($acc['relations'])),
            'imports'     => implode("\n", array_unique($acc['imports'])),
            'models'      => $acc['models'],
            'relationMap' => $acc['relationMap'],
        ];
    }

    private function detectBelongsToRelations(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE');

        $rows = DB::select(
            "SELECT
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             WHERE kcu.TABLE_SCHEMA = ?
               AND kcu.TABLE_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME IS NOT NULL",
            [$database, $this->tableName]
        );

        $relations = [];
        $imports   = [];
        $models    = [];
        $map       = [];

        foreach ($rows as $r) {
            $fkColumn     = $r->COLUMN_NAME;
            $refTable     = $r->REFERENCED_TABLE_NAME;
            $refColumn    = $r->REFERENCED_COLUMN_NAME ?? 'id';

            $relatedModel = Str::studly(Str::singular($refTable));
            $method       = Str::camel(Str::beforeLast($fkColumn, '_id')) ?: Str::camel($relatedModel);
            $fullModelNs  = "{$this->namespace}\\{$relatedModel}";

            $imports[] = "use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;";
            $imports[] = "use {$fullModelNs};";

            $code =
                "public function {$method}(): BelongsTo\n" .
                "    {\n" .
                "        return \$this->belongsTo({$relatedModel}::class, '{$fkColumn}', '{$refColumn}');\n" .
                "    }";

            $relations[]     = $code;
            $map[$method]    = $code;
            $models[$relatedModel] = ['table' => $refTable, 'module' => $this->moduleName];
        }

        return ['relations' => $relations, 'imports' => $imports, 'models' => $models, 'relationMap' => $map];
    }

    private function detectHasManyRelations(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE');

        $rows = DB::select(
            "SELECT
                kcu.TABLE_NAME AS CHILD_TABLE,
                kcu.COLUMN_NAME AS CHILD_FK_COLUMN
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
             WHERE kcu.TABLE_SCHEMA = ?
               AND kcu.REFERENCED_TABLE_NAME = ?
               AND kcu.REFERENCED_COLUMN_NAME = 'id'",
            [$database, $this->tableName]
        );

        $relations = [];
        $imports   = [];
        $models    = [];
        $map       = [];

        foreach ($rows as $r) {
            $childTable  = $r->CHILD_TABLE;
            $childFk     = $r->CHILD_FK_COLUMN;

            if ($this->looksLikePivotTable($childTable)) {
                continue;
            }

            $relatedModel = Str::studly(Str::singular($childTable));
            $method       = Str::camel(Str::pluralStudly($relatedModel));
            $fullModelNs  = "{$this->namespace}\\{$relatedModel}";

            $imports[] = "use Illuminate\\Database\\Eloquent\\Relations\\HasMany;";
            $imports[] = "use {$fullModelNs};";

            $code =
                "public function {$method}(): HasMany\n" .
                "    {\n" .
                "        return \$this->hasMany({$relatedModel}::class, '{$childFk}', 'id');\n" .
                "    }";

            $relations[]     = $code;
            $map[$method]    = $code;
            $models[$relatedModel] = ['table' => $childTable, 'module' => $this->moduleName];
        }

        return ['relations' => $relations, 'imports' => $imports, 'models' => $models, 'relationMap' => $map];
    }

    private function detectBelongsToManyRelations(): array
    {
        $database = config('database.connections.' . config('database.default') . '.database') ?? env('DB_DATABASE');

        $rows = DB::select(
            "SELECT
                a.TABLE_NAME                              AS PIVOT_TABLE,
                a.COLUMN_NAME                             AS CURRENT_FK,
                a.REFERENCED_COLUMN_NAME                  AS CURRENT_PK,
                b.REFERENCED_TABLE_NAME                   AS RELATED_TABLE,
                b.COLUMN_NAME                             AS RELATED_FK,
                b.REFERENCED_COLUMN_NAME                  AS RELATED_PK
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE a
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE b
               ON b.TABLE_SCHEMA = a.TABLE_SCHEMA
              AND b.TABLE_NAME = a.TABLE_NAME
              AND b.REFERENCED_TABLE_NAME IS NOT NULL
             WHERE a.TABLE_SCHEMA = ?
               AND a.REFERENCED_TABLE_NAME = ?
               AND a.REFERENCED_TABLE_NAME <> b.REFERENCED_TABLE_NAME",
            [$database, $this->tableName]
        );

        $seen = [];
        $relations = [];
        $imports   = [];
        $models    = [];
        $map       = [];

        foreach ($rows as $r) {
            $pivotTable   = $r->PIVOT_TABLE;

            if (!$this->looksLikePivotTable($pivotTable)) {
                continue;
            }

            $currentFk    = $r->CURRENT_FK;
            $currentPk    = $r->CURRENT_PK ?: 'id';
            $relatedTable = $r->RELATED_TABLE;
            $relatedFk    = $r->RELATED_FK;
            $relatedPk    = $r->RELATED_PK ?: 'id';

            $key = "{$pivotTable}|{$relatedTable}|{$currentFk}|{$relatedFk}";
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $relatedModel = Str::studly(Str::singular($relatedTable));
            $method       = Str::camel(Str::pluralStudly($relatedModel));
            $fullModelNs  = "{$this->namespace}\\{$relatedModel}";

            $imports[] = "use Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany;";
            $imports[] = "use {$fullModelNs};";

            $code =
                "public function {$method}(): BelongsToMany\n" .
                "    {\n" .
                "        return \$this->belongsToMany(\n" .
                "            {$relatedModel}::class,\n" .
                "            '{$pivotTable}',\n" .
                "            '{$currentFk}',\n" .
                "            '{$relatedFk}',\n" .
                "            '{$currentPk}',\n" .
                "            '{$relatedPk}'\n" .
                "        );\n" .
                "    }";

            $relations[]     = $code;
            $map[$method]    = $code;
            $models[$relatedModel] = ['table' => $relatedTable, 'module' => $this->moduleName];
        }

        return ['relations' => $relations, 'imports' => $imports, 'models' => $models, 'relationMap' => $map];
    }

    private function looksLikePivotTable(string $table): bool
    {
        if (Str::contains($table, ['_', '-'])) {
            return true;
        }
        return false;
    }

    private function getOtherTraits(): array
    {
        $databaseName = config('database.connections.' . config('database.default') . '.database')
            ?? env('DB_DATABASE');

        $schemaColumns = DB::table('information_schema.columns')
            ->select(['COLUMN_NAME', 'DATA_TYPE', 'COLUMN_TYPE', 'IS_NULLABLE', 'CHARACTER_MAXIMUM_LENGTH'])
            ->where('TABLE_SCHEMA', $databaseName)
            ->where('TABLE_NAME', $this->tableName)
            ->get()
            ->all();

        $getColumnName = fn(object $col) => Str::of($col->COLUMN_NAME)->trim()->toString();
        $getDataType   = fn(object $col) => Str::of($col->DATA_TYPE)->lower()->trim()->toString();

        $hasUuidColumn   = false;
        $datetimeColumns = [];
        $dateOnlyColumns = [];

        foreach ($schemaColumns as $schemaColumn) {
            $columnName = $getColumnName($schemaColumn);
            $dataType   = $getDataType($schemaColumn);

            if ($columnName === 'uuid') {
                $hasUuidColumn = true;
            }

            if (in_array($dataType, ['datetime', 'timestamp'], true)) {
                $datetimeColumns[] = $columnName;
            } elseif ($dataType === 'date') {
                $dateOnlyColumns[] = $columnName;
            }
        }

        $useStatements = [];
        $traitNames    = [];

        if ($hasUuidColumn) {
            $useStatements[] = 'use App\Traits\HasUuid;';
            $traitNames[]    = 'HasUuid';
        }
        if (!empty($datetimeColumns) || !empty($dateOnlyColumns)) {
            $useStatements[] = 'use App\Traits\HasUtcDates;';
            $traitNames[]    = 'HasUtcDates';
        }

        $useStatements = array_values(array_unique($useStatements));
        $traitNames    = array_values(array_unique($traitNames));

        $useStatementsBlock = implode("\n", $useStatements);
        $traitsBlock        = $traitNames ? 'use ' . implode(', ', $traitNames) . ';' : '';

        $classProperties = [];

        if (!empty($datetimeColumns)) {
            $classProperties[] = "protected \$dateFormat = 'Y-m-d H:i:s';";
        } elseif (!empty($dateOnlyColumns)) {
            $classProperties[] = "protected \$dateFormat = 'Y-m-d';";
        }

        if (!empty($datetimeColumns) || !empty($dateOnlyColumns)) {
            $utcDateAttributes = array_values(array_unique(array_merge($datetimeColumns, $dateOnlyColumns)));
            $utcDateAttributes = array_values(array_diff($utcDateAttributes, ['created_at', 'updated_at']));
            $quotedAttributes  = implode(', ', array_map(fn($name) => "'{$name}'", $utcDateAttributes));
            $classProperties[] = "protected array \$utcDateAttributes = [{$quotedAttributes}];";
        }

        $castLines = [];
        foreach ($datetimeColumns as $columnName) {
            $castLines[] = "    '{$columnName}' => 'immutable_datetime'";
        }
        foreach ($dateOnlyColumns as $columnName) {
            $castLines[] = "    '{$columnName}' => 'immutable_date'";
        }
        if ($castLines) {
            $classProperties[] = "protected \$casts = [\n" . implode(",\n", $castLines) . "\n];";
        }

        return [
            'namespace' => $useStatementsBlock,
            'traits'    => $traitsBlock,
            'props'     => $classProperties,
        ];
    }

    /** Extrait les méthodes de relation existantes (BelongsTo|HasMany|BelongsToMany|HasOne) : [method => code] */
    private function extractRelationMethods(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $map = [];
        $pattern = '/public\s+function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(\)\s*:\s*(BelongsToMany|BelongsTo|HasMany|HasOne)[\s\S]*?\n\s*}\s*/m';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $method = $m[1];
                $code   = $m[0];
                $map[$method] = trim($code);
            }
        }
        return $map;
    }

    /** Extrait les lignes `use ...;` existantes pour les préserver */
    private function extractImportLines(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $uses = [];
        if (preg_match_all('/^use\s+[^;]+;/m', $content, $m)) {
            foreach ($m[0] as $line) {
                $uses[] = trim($line);
            }
        }
        return array_values(array_unique($uses));
    }
}
