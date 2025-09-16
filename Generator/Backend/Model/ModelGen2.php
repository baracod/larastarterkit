<?php

declare(strict_types=1);

namespace App\Generator\Backend\Model;

use App\Generator\Utils\GeneratorTrait;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * ModelGen
 * - Lit une définition JSON: ModuleData/{module}/{modelKey}.json
 * - Hydrate les méta (namespace, fqcn, path, table, fillable, relations, traits)
 * - Construit le code depuis le stub et l’écrit sur disque
 * - (Optionnel) Patch le parent avec hasMany quand isParentHasMany=true
 */
final class ModelGen2
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

    /**
     * @param  string  $modelKey  Clé/nom du fichier JSON dans ModuleData/{module}/
     * @param  string  $moduleName  Nom du module
     */
    public function __construct(string $modelKey, string $moduleName)
    {
        $this->modelKey = $modelKey;
        $this->moduleName = $moduleName;

        $data = $this->readData();
        if (! $data) {
            throw new RuntimeException("Fichier JSON introuvable pour {$moduleName}/{$modelKey}");
        }

        $this->hydrateFromJson($data);
        $this->generate();
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Hydratation & lecture JSON
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Lit le JSON de définition pour ce modelKey/moduleName.
     *
     * @return array<string,mixed>|null
     */
    public function readData(): ?array
    {
        $filePath = $this->jsonPath($this->moduleName);
        if (! File::exists($filePath)) {
            return null;
        }
        $content = File::get($filePath);
        $data = json_decode($content, true);
        if (isset($data['models'][$this->modelKey])) {
            return $data['models'][$this->modelKey];
        }

        return null;
    }

    /**
     * Écrit le JSON de définition puis renvoie une instance ModelGen basée dessus.
     *
     * @param  array<string,mixed>  $data
     */
    public static function writeData(array $data): self
    {
        if (empty($data['moduleName'] ?? null) || empty($data['key'] ?? null)) {
            throw new InvalidArgumentException("Les clés 'moduleName' et 'key' sont obligatoires.");
        }

        $filePath = self::jsonPath((string) $data['moduleName'], ensureDir: true);

        dd($filePath);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('JSON encode error: '.json_last_error_msg());
        }

        File::put($filePath, $json);

        return new self((string) $data['key'], (string) $data['moduleName']);
    }

    private static function jsonPath(string $moduleName, bool $ensureDir = false): string
    {
        $dir = base_path('ModuleData');

        if ($ensureDir) {
            File::ensureDirectoryExists($dir, 0755);
        }

        // Un seul fichier par module (ex: ModuleData/blog.json)
        return $dir.DIRECTORY_SEPARATOR.Str::kebab($moduleName).'.json';
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function hydrateFromJson(array $data): void
    {
        // Champs de base
        $this->modelName = (string) ($data['name'] ?? '');
        $this->tableName = (string) ($data['tableName'] ?? $this->guessTableName($this->modelName));
        $this->moduleName = (string) ($data['moduleName'] ?? $this->moduleName);

        // Namespace/FQCN/Path déduits proprement si absents
        $this->namespace = (string) ($data['namespace'] ?? "Modules\\{$this->moduleName}\\Models");
        $this->fqcn = (string) ($data['fqcn'] ?? "{$this->namespace}\\{$this->modelName}");
        $this->path = (string) ($data['path'] ?? base_path("Modules/{$this->moduleName}/Models/{$this->modelName}.php"));

        // Fillable peut être fourni en strings ou objets {name,...} -> on normalise
        $this->fillable = $this->normalizeFillable($data['fillable'] ?? []);

        // Relations (on accepte belongsTo / hasMany / belongsToMany, morph* si présents on ignore proprement)
        $this->relations = array_values((array) ($data['relations'] ?? []));

        // Traits facultatifs (namespaces complets)
        $this->traits = array_values(array_filter((array) ($data['traits'] ?? []), 'is_string'));

        // Props divers (casts/hidden/dates/guarded/primaryKey/incrementing/keyType/timestamps, etc.)
        $this->extraProps = (array) ($data['props'] ?? []);
    }

    /**
     * @return list<array{name:string,type?:string,defaultValue?:mixed,customizedType?:string}>
     */
    private function normalizeFillable(mixed $fillable): array
    {
        $out = [];

        if (is_array($fillable)) {
            foreach ($fillable as $f) {
                if (is_string($f)) {
                    $out[] = ['name' => $f];
                } elseif (is_array($f) && isset($f['name'])) {
                    $out[] = [
                        'name' => (string) $f['name'],
                        'type' => isset($f['type']) ? (string) $f['type'] : null,
                        'defaultValue' => $f['defaultValue'] ?? null,
                        'customizedType' => isset($f['customizedType']) ? (string) $f['customizedType'] : null,
                    ];
                }
            }
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

    public function generate(): void
    {
        // 1) Préparer imports + methods à partir des relations
        $relRender = $this->renderRelations($this->relations);
        $importsText = trim($relRender['class']);
        $methodsText = trim($relRender['methods']);

        // 2) Ajouter imports des traits éventuels
        $traitImports = [];
        $traitUses = [];
        foreach ($this->traits as $fqTrait) {
            // ex: Illuminate\Database\Eloquent\SoftDeletes
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
        $fillableText = $this->renderPhpArrayItems($fillableNames, 8 + 4); // aligné avec stub

        // 4) Props additionnels (casts, hidden, dates, timestamps=false, etc.)
        $propsText = $this->renderExtraProps($this->extraProps);

        // 5) Charger le stub et remplacer
        $stubPath = base_path('app/Generator/Backend/Stubs/backend/Model.stub');
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
            '{{ traitNames }}' => $traitNames, // ex: use SoftDeletes;
            '{{ props }}' => $propsText,  // ex: protected $casts = [...];
        ];

        $content = strtr($template, $replacements);

        File::ensureDirectoryExists(\dirname($this->path), 0755);
        File::put($this->path, $content);
        // 6) Optionnel: générer hasMany côté parent si demandé
        $this->setParentHasMany($this->relations);
    }

    /**
     * Rend les éléments d’un tableau PHP en lignes indentées pour un tableau PHP inline.
     * Exemple: 'email',\n            'name'
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

        // patterns connus : guarded, hidden, casts, dates, timestamps, primaryKey, incrementing, keyType
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

            // Imports du modèle lié
            if ($fqcn !== '') {
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
                // relation non gérée → on ignore proprement
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
                // belongsToMany(Model::class, 'pivot', 'this_fk', 'other_fk')
                if ($pivotTable && is_array($pivotFks)) {
                    $call = "\$this->belongsToMany({$class}::class, '{$pivotTable}', '{$pivotFks['foreignPivotKey']}', '{$pivotFks['relatedPivotKey']}')";
                } elseif ($pivotTable) {
                    $call = "\$this->belongsToMany({$class}::class, '{$pivotTable}')";
                } else {
                    $call = "\$this->belongsToMany({$class}::class)";
                }
            }

            // Méthode
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

        // Dédupe imports
        $imports = array_values(array_unique(array_filter($imports)));

        return [
            'class' => implode("\n", $imports),
            'methods' => implode("\n\n", array_map('trim', $methods)),
        ];
    }

    /**
     * Applique (si demandé) la création de hasMany dans le **parent** des relations belongsTo.
     * Ne réécrit pas ce modèle-ci ; patch les modèles parents via ModelPatcher.
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

            // Parent = modèle lié ($r['model'])
            $parentFqcn = (string) ($r['model']['fqcn'] ?? '');
            $parentPath = (string) ($r['model']['path'] ?? '');
            $parentClass = (string) ($r['model']['name'] ?? 'Model');

            if ($parentPath === '' || ! File::exists($parentPath)) {
                // si le parent n’existe pas encore, on n’échoue pas (peut être généré plus tard)
                continue;
            }

            $sig = 'HasMany';

            $imports = [
                "use {$this->fqcn};",
                "use Illuminate\\Database\\Eloquent\\Relations\\{$sig};",
            ];

            $foreignKey = $r['foreignKey'] ?? null;
            $ownerKey = $r['ownerKey'] ?? null;

            // Méthode côté parent : nom par défaut = camel(plural(model courant))
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
