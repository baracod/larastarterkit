<?php

declare(strict_types=1);

namespace App\Generator\Backend\Model;

use App\Generator\Utils\GeneratorTrait;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

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

    /** @var string[] */
    private array $fillable = [];

    /** @var array<int,array<string,mixed>> */
    private array $relations = [];

    private string $modelKey;

    private string $path = '';

    private string $fqcn = '';

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

        // Hydrate avec garde-fous
        $this->modelName = (string) ($data['name'] ?? '');
        $this->tableName = (string) ($data['tableName'] ?? '');
        $this->moduleName = (string) ($data['moduleName'] ?? $this->moduleName);
        $this->fillable = array_values((array) ($data['fillable'] ?? []));
        $this->relations = array_values((array) ($data['relations'] ?? []));

        // Namespace / FQCN / Path: déduits proprement si absents
        $this->namespace = (string) ($data['namespace'] ?? "Modules\\{$this->moduleName}\\Models");
        $this->fqcn = (string) ($data['fqcn'] ?? "{$this->namespace}\\{$this->modelName}");
        $this->path = (string) ($data['path'] ?? base_path("Modules/{$this->moduleName}/Models/{$this->modelName}.php"));

        // Génère la classe
        $this->generateModel();
    }

    // region Getter
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

    /**
     * @return string[]
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
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
    // endregion

    /**
     * Écrit le JSON de définition puis renvoie une instance ModelGen basée dessus.
     */
    public static function writeData(array $data): self
    {
        if (empty($data['moduleName'] ?? null) || empty($data['key'] ?? null)) {
            throw new InvalidArgumentException("Les clés 'moduleName' et 'key' sont obligatoires.");
        }

        $directoryPath = base_path("ModuleData/{$data['moduleName']}");
        $filePath = "{$directoryPath}/{$data['key']}.json";

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('JSON encode error: '.json_last_error_msg());
        }

        // 0755 (octal) — pas 777 en décimal
        File::ensureDirectoryExists($directoryPath, 0755);
        File::put($filePath, $json);

        return new self((string) $data['key'], (string) $data['moduleName']);
    }

    /**
     * Lit le JSON de définition pour ce modelKey/moduleName.
     *
     * @return array<string,mixed>|null
     */
    public function readData(): ?array
    {
        $directoryPath = base_path("ModuleData/{$this->moduleName}");
        $filePath = "{$directoryPath}/{$this->modelKey}.json";

        if (! File::exists($filePath)) {
            return null;
        }

        $content = File::get($filePath);
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Construit le contenu du modèle à partir du stub et l’écrit sur disque.
     */
    public function generateModel(): void
    {
        // (Optionnel / Préparatif) Déterminer les hasMany côté parent
        $parentRelations = $this->setParentHasMany($this->relations); // garde la logique (pas d’écriture parent ici)

        $stubPath = base_path('app/Generator/Backend/Stubs/backend/Model.stub');
        if (! File::exists($stubPath)) {
            throw new RuntimeException("Stub introuvable: {$stubPath}");
        }
        $template = File::get($stubPath);

        // Rendu des relations => imports + méthodes
        $relRender = $this->renderRelations($this->relations);
        $importsText = trim($relRender['class']);
        $methodsText = trim($relRender['methods']);

        // Fillable formaté (indentation 8 espaces par défaut)
        $fillableText = $this->renderPhpArrayItems(array_map(fn ($field) => $field['name'], $this->fillable), 12);

        $replacements = [
            '{{ namespace }}' => $this->namespace,
            '{{ modelName }}' => $this->modelName,
            '{{ tableName }}' => $this->tableName,
            '{{ fillable }}' => $fillableText,
            '{{ relations }}' => $methodsText,
            '{{ imports }}' => $importsText,
            '{{ traitNamespaces }}' => '',  // réservé si tu ajoutes des traits
            '{{ traitNames }}' => '',
            '{{ props }}' => '',
        ];

        $content = strtr($template, $replacements);

        File::ensureDirectoryExists(\dirname($this->path), 0755);
        File::put($this->path, $content);
    }

    /**
     * Rend les éléments d’un tableau PHP en lignes indentées pour un tableau PHP inline.
     * Exemple: 'email',\n            'name'
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
     * Construit le code des relations Eloquent (imports + méthodes).
     *
     * @param  array<int,array<string,mixed>>  $relations
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

            if ($fqcn !== '') {
                $imports[] = "use {$fqcn};";
            }

            if ($type === 'belongsTo') {
                $sig = 'BelongsTo';
                $imports[] = "use \\Illuminate\\Database\\Eloquent\\Relations\\{$sig};";

                $call = $foreignKey && $ownerKey
                    ? "\$this->belongsTo({$class}::class, '{$foreignKey}', '{$ownerKey}')"
                    : ($foreignKey
                        ? "\$this->belongsTo({$class}::class, '{$foreignKey}')"
                        : "\$this->belongsTo({$class}::class)"
                    );

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

            if ($type === 'hasMany') {
                $sig = 'HasMany';
                $imports[] = "use \\Illuminate\\Database\\Eloquent\\Relations\\{$sig};";

                $call = $foreignKey && $ownerKey
                    ? "\$this->hasMany({$class}::class, '{$foreignKey}', '{$ownerKey}')"
                    : ($foreignKey
                        ? "\$this->hasMany({$class}::class, '{$foreignKey}')"
                        : "\$this->hasMany({$class}::class)"
                    );

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
        }

        // Déduplique proprement
        $imports = array_values(array_unique(array_filter($imports)));

        return [
            'class' => implode("\n", $imports),
            'methods' => implode("\n\n", array_map('trim', $methods)),
        ];
    }

    /**
     * Prépare les hasMany côté parent à partir des belongsTo marqués isParentHasMany.
     * (Ici on **conserve la logique** : calcul sans écrire dans le parent.)
     *
     * @param  array<int,array<string,mixed>>  $relations
     * @return array{class:string,methods:string}
     */
    private function setParentHasMany(array $relations): array
    {
        if (empty($relations)) {
            return ['class' => '', 'methods' => ''];
        }

        $relations = $this->relations;

        $imports = [];
        $methods = [];

        foreach ($relations as $r) {
            $type = (string) ($r['type'] ?? '');
            $isParentHasMany = (bool) ($r['isParentHasMany'] ?? false);
            $path = (string) ($r['model']['path'] ?? '');
            if ($type !== 'belongsTo' || ! $isParentHasMany) {
                continue;
            }

            // Parent: c’est le modèle courant ($this)
            $sig = 'HasMany';
            $parentName = $this->modelName; // nom de la méthode côté parent
            $childClass = (string) ($r['name'] ?? 'Model'); // classe liée (enfant)
            $childFqcn = (string) ($r['model']['fqcn'] ?? '');

            $imports[] = "use {$this->fqcn};";
            $imports[] = "use Illuminate\\Database\\Eloquent\\Relations\\{$sig};";

            $foreignKey = $r['foreignKey'] ?? null;
            $ownerKey = $r['ownerKey'] ?? null;

            $call = $foreignKey && $ownerKey
                ? "\$this->hasMany({$this->modelName}::class, '{$foreignKey}', '{$ownerKey}')"
                : ($foreignKey
                    ? "\$this->hasMany({$this->modelName}::class, '{$foreignKey}')"
                    : "\$this->hasMany({$this->modelName}::class)"
                );

            $methods[] = <<<PHP
                /**
                 * Relation {$sig} {$childClass} (côté parent).
                 */
                public function {$parentName}(): {$sig}
                {
                    return {$call};
                }
            PHP;

            $code = file_get_contents($path);

            $patched = ModelPatcher::apply($code, $imports, [], $methods);

            file_put_contents($path, $patched);
        }

        $imports = array_values(array_unique(array_filter($imports)));

        return [
            'class' => implode("\n", $imports),
            'methods' => implode("\n\n", array_map('trim', $methods)),
        ];
    }
}
