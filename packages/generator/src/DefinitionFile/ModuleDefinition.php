<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile;

use Baracod\Larastarterkit\Generator\DefinitionFile\Contracts\ArrayConvertible;
use DomainException;
use Nwidart\Modules\Facades\Module;

use function file_put_contents;
use function json_encode;

/**
 * Class ModuleDefinition
 *
 * Représente un module (métadonnées NWIDART + collection de modèles).
 *
 * @phpstan-type ModuleArray array{
 *   name: string,
 *   alias: string,
 *   description: string,
 *   keywords: list<string>,
 *   priority: int,
 *   providers: list<string>,
 *   files: list<string>,
 *   module: string,
 *   models: array<string, array<string, mixed>>
 * }
 *
 * @example
 * $module = ModuleDefinition::fromArray($jsonArray);
 * $model  = $module->model('blog-author');
 */
final class ModuleDefinition implements ArrayConvertible
{
    /** @var array<string, ModelDefinition> */
    private array $models = [];

    /** Nom logique du module (ex: "Blog"). */
    private string $name;

    /** Alias du module (ex: "blog"). */
    private string $alias;

    /** Description libre. */
    private string $description = '';

    /** @var list<string> */
    private array $keywords = [];

    /** Priorité d’ordre (entier). */
    private int $priority = 0;

    /** @var list<string> Providers FQCN. */
    private array $providers = [];

    /** @var list<string> Fichiers supplémentaires. */
    private array $files = [];

    /**
     * Chemin du fichier JSON d’origine (optionnel, pour la persistance).
     */
    private ?string $filePath = null;

    /**
     * @param  string  $module  Nom interne du module (historiquement utilisé)
     *
     * @throws DomainException Si vide
     */
    public function __construct(private string $module)
    {
        if ($module === '') {
            throw new DomainException('Le nom du module est requis.');
        }

        // Valeurs par défaut cohérentes si non renseignées
        $this->name = $module;
        $this->alias = strtolower($module);
        $this->filePath = Module::getModulePath($module).'module.json';
    }

    /**
     * Construit un module à partir du tableau racine JSON.
     *
     * @param  array<string, mixed>  $a
     * @return static
     */
    public static function fromArray(array $a): self
    {
        // "module" reste la source de vérité historique ; sinon fallback sur "name"
        $moduleName = (string) ($a['module'] ?? ($a['name'] ?? ''));
        if ($moduleName === '') {
            throw new DomainException('"module" (ou "name") est requis dans la définition du module.');
        }

        $m = new self($moduleName);

        // Métadonnées NWIDART (toutes optionnelles)
        $m->name = (string) ($a['name'] ?? $moduleName);
        $m->alias = (string) ($a['alias'] ?? strtolower($moduleName));
        $m->description = (string) ($a['description'] ?? '');
        $m->keywords = array_values(array_map('strval', is_array($a['keywords'] ?? null) ? $a['keywords'] : []));
        $m->priority = isset($a['priority']) ? (int) $a['priority'] : 0;
        $m->providers = array_values(array_map('strval', is_array($a['providers'] ?? null) ? $a['providers'] : []));
        $m->files = array_values(array_map('strval', is_array($a['files'] ?? null) ? $a['files'] : []));

        // Modèles
        $rawModels = $a['models'] ?? [];
        if (! is_array($rawModels)) {
            throw new DomainException('"models" doit être un objet {key: model}.');
        }

        foreach ($rawModels as $key => $rawModel) {
            $model = ModelDefinition::fromArray((array) $rawModel + ['key' => (string) $key]);
            $m->models[$model->key()] = $model;
        }

        return $m;
    }

    /**
     * Nom du module (historique) — correspond à la clé "module".
     * Pour le nom d’affichage NWIDART, voir {@see displayName()}.
     */
    public function name(): string
    {
        return $this->module;
    }

    /** Nom d’affichage (clé "name"). */
    public function displayName(): string
    {
        return $this->name;
    }

    /** Alias (clé "alias"). */
    public function alias(): string
    {
        return $this->alias;
    }

    /** Description. */
    public function description(): string
    {
        return $this->description;
    }

    /** @return list<string> */
    public function keywords(): array
    {
        return $this->keywords;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /** @return list<string> */
    public function providers(): array
    {
        return $this->providers;
    }

    /** @return list<string> */
    public function files(): array
    {
        return $this->files;
    }

    /**
     * Retourne le chemin du fichier JSON d’origine, si connu.
     */
    public function filePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Définit ou met à jour le chemin du fichier JSON associé.
     *
     * @return $this
     */
    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * Retourne tous les modèles (indexés par clé).
     *
     * @return array<string, ModelDefinition>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * Récupère un modèle par sa clé.
     *
     *
     * @throws DomainException Si le modèle n’existe pas
     */
    public function model(string $key): ModelDefinition
    {
        if (! isset($this->models[$key])) {
            throw new DomainException("Modèle inexistant: {$key}");
        }

        return $this->models[$key];
    }

    /**
     * Crée un nouveau modèle (échoue s’il existe déjà).
     *
     *
     * @throws DomainException Si un modèle avec la même clé existe
     */
    public function createModel(ModelDefinition $model): ModelDefinition
    {
        $key = $model->key();

        if (isset($this->models[$key])) {
            throw new DomainException("Le modèle '{$key}' existe déjà.");
        }

        return $this->models[$key] = $model;
    }

    /**
     * Ajoute ou remplace un modèle par sa clé.
     */
    public function upsertModel(ModelDefinition $model): ModelDefinition
    {
        return $this->models[$model->key()] = $model;
    }

    /**
     * Supprime un modèle si présent.
     */
    public function deleteModel(string $key): void
    {
        unset($this->models[$key]);
    }

    /**
     * Sérialise en tableau conforme au schéma JSON.
     *
     * @return ModuleArray
     */
    public function toArray(): array
    {
        $models = [];
        foreach ($this->models as $key => $m) {
            $models[$key] = $m->toArray();
        }

        return [
            // Métadonnées NWIDART
            'name' => $this->name,
            'alias' => $this->alias,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'priority' => $this->priority,
            'providers' => $this->providers,
            'files' => $this->files,

            // Compat & clé historique
            'module' => $this->module,

            // Modèles
            'models' => $models,
        ];
    }

    /**
     * Persiste le module courant dans un fichier JSON.
     * Si aucun chemin n’est fourni, utilise {@see filePath()}.
     *
     * @param  string|null  $override  Chemin alternatif de sauvegarde
     *
     * @throws DomainException Si aucun chemin connu ou échec d’encodage
     */
    public function save(?string $override = null): void
    {
        $path = $override ?? $this->filePath;

        if (! $path) {
            throw new DomainException('Aucun chemin de sauvegarde connu.');
        }

        $json = json_encode(
            $this->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new DomainException('Échec d’encodage JSON.');
        }

        file_put_contents($path, $json);
    }
}
