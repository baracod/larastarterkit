<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Model;

use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition as DField;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Baracod\Larastarterkit\Generator\Utils\GeneratorTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

/**
 * ModelGen
 * - Lit la définition typée dans: ModuleData/{module}.json (via DefinitionStore)
 * - Hydrate (namespace, fqcn, path, table, fillable, relations, traits, props)
 * - Construit le code depuis le stub et l’écrit sur disque
 * - (Optionnel) Patch le parent avec hasMany quand isParentHasMany=true
 */
final class ModelGen
{
    use GeneratorTrait;

    private const MAX_RECURSION_DEPTH = 2;

    /** @var array<string,bool> */
    private static array $generated = [];

    /** @var array<string,bool> */
    private static array $inProgress = [];

    public string $tableName = '';

    public string $modelName = '';

    public string $moduleName;

    private string $namespace = '';

    /** @var list<array{name:string,type?:string,defaultValue?:mixed,customizedType?:string}> */
    private array $fillable = [];

    /** @var list<array<string,mixed>> */
    private array $relations = [];

    /** @var list<string> */
    private array $traits = [];

    private string $modelKey;

    private string $path = '';

    private string $fqcn = '';

    /** @var array<string,mixed> Props divers à injecter dans {{ props }} (casts, hidden, dates, etc.) */
    private array $extraProps = [];

    /** Une factory existe pour ce modèle : newFactory() est déclarée. */
    private bool $hasFactory = false;

    /** Store chargé pour le module courant */
    private DefinitionStore $store;

    /**
     * @param  string  $modelKey  Clé du modèle dans le JSON (ex: "blog-author")
     * @param  string  $moduleName  Nom du module (Studly ou équivalent)
     */
    public function __construct(string $modelKey, string $moduleName)
    {
        $this->modelKey = $modelKey;
        $this->moduleName = Str::studly($moduleName);

        $dfModel = $this->readModel();         // DFModel
        $this->hydrateFromDefinition($dfModel); // hydrate propriétés locales
        $this->buildAutoCasts();               // génère automatiquement les casts pour dates
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Lecture DefinitionStore & DFModel
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Charge le DefinitionStore du module et renvoie le DFModel ciblé.
     *
     * @throws RuntimeException si le store ou le modèle est introuvable
     */
    private function readModel(): DFModel
    {
        $filePath = self::jsonPath($this->moduleName);

        if (! File::exists($filePath)) {
            throw new RuntimeException("Fichier de définition introuvable: {$filePath}");
        }

        try {
            $this->store = DefinitionStore::fromFile($filePath);
        } catch (\Throwable $e) {
            throw new RuntimeException("Le JSON du module {$this->moduleName} est invalide: {$e->getMessage()}");
        }

        try {
            return $this->store->module()->model($this->modelKey);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                "La clé modèle '{$this->modelKey}' est absente du module '{$this->moduleName}'."
            );
        }
    }

    /**
     * Écrit/Met à jour la définition du modèle dans le store du module puis renvoie une instance ModelGen.
     *
     * @param  array<string,mixed>  $data  Tableau de définition d’un modèle (conforme à DFModel::fromArray)
     *                                     requis: key, moduleName, name, tableName, namespace, fqcn, path, fillable, relations, backend, frontend...
     */
    public static function writeData(array $data): self
    {
        if (empty($data['moduleName'] ?? null) || empty($data['key'] ?? null)) {
            throw new InvalidArgumentException("Les clés 'moduleName' et 'key' sont obligatoires.");
        }

        $moduleName = Str::studly((string) $data['moduleName']);
        $key = (string) $data['key'];

        $filePath = self::jsonPath($moduleName);

        // Ouvrir store existant ou en créer un minimal
        if (File::exists($filePath)) {
            $store = DefinitionStore::fromFile($filePath);
        } else {
            // Store minimal compatible avec la nouvelle structure
            $store = DefinitionStore::fromArray([
                'name' => $moduleName,
                'alias' => Str::kebab($moduleName),
                'description' => '',
                'keywords' => [],
                'priority' => 0,
                'providers' => [],
                'files' => [],
                'module' => $moduleName,
                'models' => [],
            ]);
        }

        // Upsert du modèle
        $modelDef = DFModel::fromArray($data);
        $store->module()->upsertModel($modelDef);
        $store->save($filePath);

        return new self($key, $moduleName);
    }

    /**
     * Construit le chemin du fichier JSON du module.
     */
    private static function jsonPath(string $moduleName): string
    {
        $path = Module::getModulePath($moduleName).'module.json';

        return $path;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Hydratation depuis DFModel
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Hydrate l’instance à partir du DFModel typé.
     */
    private function hydrateFromDefinition(DFModel $m): void
    {
        // Champs de base
        $this->modelName = (string) $m->name();
        $this->tableName = (string) ($m->tableName() ?: $this->guessTableName($this->modelName));
        $this->moduleName = (string) $m->moduleName();

        // Namespace/FQCN/Path (avec valeurs par défaut si absents)
        $this->namespace = (string) ($m->namespace() ?: "Modules\\{$this->moduleName}\\Models");
        $this->fqcn = (string) ($m->fqcn() ?: "{$this->namespace}\\{$this->modelName}");
        $this->path = (string) ($m->path() ?: base_path("Modules/{$this->moduleName}/app/Models/{$this->modelName}.php"));

        // Fillable: normaliser vers liste d'arrays {name,type,defaultValue,customizedType}
        $this->fillable = $this->normalizeFillableFromFields($m->fields());

        // Relations: structure libre telle que stockée dans DFModel
        $this->relations = array_values($m->relations() ?? []);

        // Traits et propriétés additionnels déclarés dans la définition
        $this->traits = array_values(array_filter((array) $m->option('traits', []), 'is_string'));
        $this->extraProps = (array) $m->option('props', []);
        if ($m->usesSoftDeletes()) {
            $this->traits[] = 'Illuminate\\Database\\Eloquent\\SoftDeletes';
        }
        if (! $m->usesTimestamps()) {
            $this->extraProps['timestamps'] ??= false;
        }
        $this->hasFactory = $m->backend()->hasFactory
            || File::exists(base_path("Modules/{$this->moduleName}/database/factories/{$this->modelName}Factory.php"));
    }

    /**
     * Génère automatiquement les casts pour les champs date/datetime/timestamp.
     * Convertit les formats ISO 8601 du frontend en formats MySQL compatibles.
     */
    private function buildAutoCasts(): void
    {
        $autoCasts = [];

        foreach ($this->fillable as $field) {
            $type = $field['type'] ?? null;
            $name = $field['name'];

            // Mapper les types FieldType vers les casts Laravel
            $castType = match ($type) {
                'date' => 'date',
                'datetime' => 'datetime',
                'timestamp' => 'timestamp',
                'boolean' => 'boolean',
                'integer' => 'integer',
                'float' => 'float',
                'json' => 'array',
                default => null,
            };

            if ($castType !== null) {
                $autoCasts[$name] = $castType;
            }
        }

        // Fusionner avec les casts existants (priorité aux casts manuels)
        if (! empty($autoCasts)) {
            $existingCasts = $this->extraProps['casts'] ?? [];
            $this->extraProps['casts'] = array_merge($autoCasts, $existingCasts);
        }
    }

    /**
     * @param  array<string,DField>|list<DField>  $fields
     * @return list<array{name:string,type?:string,defaultValue?:mixed,customizedType?:string}>
     */
    private function normalizeFillableFromFields(array $fields): array
    {
        // Supporte array indexé par clé ou liste
        $list = array_values($fields);

        $out = [];
        foreach ($list as $f) {
            if (! $f instanceof DField) {
                continue;
            }
            $out[] = [
                'name' => $f->name,
                'type' => $f->type->value ?? null,
                'defaultValue' => $f->defaultValue ?? null,
                'customizedType' => $f->customizedType ?? null,
            ];
        }

        return $out;
    }

    private function guessTableName(string $model): string
    {
        return Str::snake(Str::pluralStudly($model));
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Génération du code
    // ─────────────────────────────────────────────────────────────────────────────

    public function generate(): bool
    {
        // 1) Préparer imports + methods à partir des relations
        $relRender = $this->renderRelations($this->relations);
        $importsText = trim($relRender['class']);
        $methodsText = trim($relRender['methods']);

        // 2) Ajouter imports des traits éventuels
        $traitImports = [];
        $traitUses = [];
        foreach ($this->traits as $fqTrait) {
            if (! \str_contains($importsText, "use {$fqTrait};")) {
                $traitImports[] = "use {$fqTrait};";
            }
            $short = ltrim(Str::afterLast($fqTrait, '\\'), '\\');
            $traitUses[] = "use {$short};";
        }
        if ($traitImports) {
            $importsText = trim($importsText."\n".implode("\n", $traitImports));
        }
        $traitNames = $traitUses ? ("\n    ".implode("\n    ", $traitUses)."\n") : '';

        // 3) Fillable → liste de noms
        $fillableNames = array_map(static fn (array $f) => $f['name'], $this->fillable);
        $fillableText = $this->renderPhpArrayItems($fillableNames, 8 + 4);

        // 4) Props additionnels (+ factory du module)
        $propsText = $this->renderExtraProps($this->extraProps);
        if ($this->hasFactory) {
            $factory = "Modules\\{$this->moduleName}\\Database\\Factories\\{$this->modelName}Factory";
            $importsText = trim($importsText."\nuse {$factory};");
            $propsText .= "\n    protected static function newFactory(): {$this->modelName}Factory\n    {\n        return {$this->modelName}Factory::new();\n    }\n";
        }

        // 5) Charger le stub et remplacer
        $stubPath = dirname(__DIR__, 2).'/Backend/Stubs/backend/Model.stub';
        if (! File::exists($stubPath)) {
            throw new RuntimeException("Stub introuvable: {$stubPath}");
        }
        $template = File::get($stubPath);

        $replacements = [
            '{{ namespace }}' => $this->namespace,
            '{{ modelName }}' => $this->modelName,
            '{{ tableName }}' => $this->tableName,
            '{{ fillable }}' => $fillableText,
            '{{ relations }}' => $methodsText ? ("\n    ".str_replace("\n", "\n    ", $methodsText)."\n") : '',
            '{{ imports }}' => $importsText,
            '{{ traitNames }}' => $traitNames,
            '{{ props }}' => $propsText,
        ];

        $content = strtr($template, $replacements);

        File::ensureDirectoryExists(\dirname($this->path), 0755);
        File::put($this->path, $content);

        // 6) Optionnel: générer hasMany côté parent si demandé
        $this->setParentHasMany($this->relations);

        // Les permissions sont gérées par le mode dédié (seeder du module), jamais comme effet de bord ici.
        return true;
    }

    /**
     * Rend les éléments d’un tableau PHP en lignes indentées.
     *
     * @param  list<string>  $items
     */
    private function renderPhpArrayItems(array $items, int $indent = 12): string
    {
        if (empty($items)) {
            return '';
        }
        $spaces = str_repeat(' ', $indent);
        $lines = array_map(
            static fn ($v) => "'".addslashes((string) $v)."'",
            $items
        );

        return implode(",\n{$spaces}", $lines);
    }

    /**
     * @param  array<string,mixed>  $props
     */
    private function renderExtraProps(array $props): string
    {
        if ($props === []) {
            return '';
        }

        $lines = [];

        if (isset($props['guarded']) && is_array($props['guarded'])) {
            $lines[] = $this->renderAssocArrayProp('protected $guarded', $props['guarded']);
        }
        if (isset($props['hidden']) && is_array($props['hidden'])) {
            $lines[] = $this->renderAssocArrayProp('protected $hidden', $props['hidden']);
        }
        if (isset($props['casts']) && is_array($props['casts'])) {
            $lines[] = $this->renderAssocArrayMap('protected $casts', $props['casts']);
        }
        if (isset($props['dates']) && is_array($props['dates'])) {
            $lines[] = $this->renderAssocArrayProp('protected $dates', $props['dates']);
        }
        if (array_key_exists('timestamps', $props)) {
            $val = $props['timestamps'] ? 'true' : 'false';
            $lines[] = "    public \$timestamps = {$val};";
        }
        if (isset($props['primaryKey']) && is_string($props['primaryKey'])) {
            $lines[] = "    protected \$primaryKey = '".addslashes($props['primaryKey'])."';";
        }
        if (array_key_exists('incrementing', $props)) {
            $val = $props['incrementing'] ? 'true' : 'false';
            $lines[] = "    public \$incrementing = {$val};";
        }
        if (isset($props['keyType']) && is_string($props['keyType'])) {
            $lines[] = "    protected \$keyType = '".addslashes($props['keyType'])."';";
        }

        return $lines ? ("\n".implode("\n", $lines)."\n") : '';
    }

    /**
     * @param  list<string>  $values
     */
    private function renderAssocArrayProp(string $decl, array $values): string
    {
        $items = $this->renderPhpArrayItems(array_map('strval', $values), 8 + 4);

        return "    {$decl} = [\n            {$items}\n    ];";
    }

    /**
     * @param  array<string,string>  $map
     */
    private function renderAssocArrayMap(string $decl, array $map): string
    {
        if ($map === []) {
            return "    {$decl} = [];";
        }
        $indent = str_repeat(' ', 12);
        $entries = [];
        foreach ($map as $k => $v) {
            $entries[] = "'".addslashes((string) $k)."' => '".addslashes((string) $v)."'";
        }

        return "    {$decl} = [\n{$indent}".implode(",\n{$indent}", $entries)."\n    ];";
    }

    /**
     * Construit le code des relations Eloquent (imports + méthodes).
     *
     * @param  list<array<string,mixed>>  $relations
     * @return array{class:string,methods:string}
     */
    private function renderRelations(array $relations): array
    {
        if (empty($relations)) {
            return ['class' => '', 'methods' => ''];
        }

        $imports = [];
        $methods = [];

        foreach ($relations as $r) {
            $type = (string) ($r['type'] ?? '');
            $name = (string) ($r['name'] ?? '');
            $class = (string) ($r['model']['name'] ?? 'Model');
            $fqcn = (string) ($r['model']['fqcn'] ?? '');
            if ($fqcn === '' && ! empty($r['model']['namespace'])) {
                $fqcn = rtrim((string) $r['model']['namespace'], '\\').'\\'.$class;
            }

            $foreignKey = $r['foreignKey'] ?? null;
            $ownerKey = $r['ownerKey'] ?? null;
            $pivotTable = $r['pivotTable'] ?? null; // belongsToMany
            $pivotFks = $r['pivotKeys'] ?? null; // ['foreignPivotKey'=>'','relatedPivotKey'=>'']

            // Imports du modèle lié (inutile dans le même espace de noms)
            if ($fqcn !== '' && Str::beforeLast($fqcn, '\\') !== $this->namespace) {
                $imports[] = "use {$fqcn};";
            }

            // Signature & import relation
            $sig = match ($type) {
                'belongsTo' => 'BelongsTo',
                'hasMany' => 'HasMany',
                'belongsToMany' => 'BelongsToMany',
                default => null
            };
            if ($sig) {
                $imports[] = "use Illuminate\\Database\\Eloquent\\Relations\\{$sig};";
            } else {
                continue;
            }

            // Appel
            $call = '';
            if ($type === 'belongsTo') {
                $call = $foreignKey && $ownerKey
                    ? "\$this->belongsTo({$class}::class, '{$foreignKey}', '{$ownerKey}')"
                    : ($foreignKey
                        ? "\$this->belongsTo({$class}::class, '{$foreignKey}')"
                        : "\$this->belongsTo({$class}::class)");
            } elseif ($type === 'hasMany') {
                $call = $foreignKey && $ownerKey
                    ? "\$this->hasMany({$class}::class, '{$foreignKey}', '{$ownerKey}')"
                    : ($foreignKey
                        ? "\$this->hasMany({$class}::class, '{$foreignKey}')"
                        : "\$this->hasMany({$class}::class)");
            } elseif ($type === 'belongsToMany') {
                if ($pivotTable && is_array($pivotFks)) {
                    $call = "\$this->belongsToMany({$class}::class, '{$pivotTable}', '{$pivotFks['foreignPivotKey']}', '{$pivotFks['relatedPivotKey']}')";
                } elseif ($pivotTable) {
                    $call = "\$this->belongsToMany({$class}::class, '{$pivotTable}')";
                } else {
                    $call = "\$this->belongsToMany({$class}::class)";
                }
            }

            $methods[] = <<<PHP
            /**
             * Relation {$sig} {$class}.
             */
            public function {$name}(): {$sig}
            {
                return {$call};
            }
            PHP;
        }

        $imports = array_values(array_unique(array_filter($imports)));

        return [
            'class' => implode("\n", $imports),
            'methods' => implode("\n\n", array_map('trim', $methods)),
        ];
    }

    /**
     * Applique (si demandé) la création de hasMany dans le parent des relations belongsTo.
     *
     * @param  list<array<string,mixed>>  $relations
     */
    private function setParentHasMany(array $relations): void
    {
        if (empty($relations)) {
            return;
        }

        foreach ($relations as $r) {
            if (($r['type'] ?? '') !== 'belongsTo' || empty($r['isParentHasMany'])) {
                continue;
            }

            $parentPath = (string) ($r['model']['path'] ?? '');
            if ($parentPath === '' || ! File::exists($parentPath)) {
                continue;
            }

            $sig = 'HasMany';

            $imports = [
                "use {$this->fqcn};",
                "use Illuminate\\Database\\Eloquent\\Relations\\{$sig};",
            ];

            $foreignKey = $r['foreignKey'] ?? null;
            $ownerKey = $r['ownerKey'] ?? null;

            $parentMethod = Str::camel(Str::pluralStudly($this->modelName));
            $call = $foreignKey && $ownerKey
                ? "\$this->hasMany({$this->modelName}::class, '{$foreignKey}', '{$ownerKey}')"
                : ($foreignKey
                    ? "\$this->hasMany({$this->modelName}::class, '{$foreignKey}')"
                    : "\$this->hasMany({$this->modelName}::class)");

            $methods = [<<<PHP
            /**
             * Relation {$sig} {$this->modelName} (côté parent).
             */
            public function {$parentMethod}(): {$sig}
            {
                return {$call};
            }
            PHP];

            $code = File::get($parentPath);
            $patched = ModelPatcher::apply($code, $imports, [], $methods);
            File::put($parentPath, $patched);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Getters
    // ─────────────────────────────────────────────────────────────────────────────

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getModelName(): string
    {
        return $this->modelName;
    }

    public function getModuleName(): string
    {
        return $this->moduleName;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /** @return list<array{name:string,type?:string,defaultValue?:mixed,customizedType?:string}> */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /** @return list<array<string,mixed>> */
    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getModelKey(): string
    {
        return $this->modelKey;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFqcn(): string
    {
        return $this->fqcn;
    }
}
