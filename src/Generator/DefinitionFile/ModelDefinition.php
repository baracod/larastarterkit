<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile;

use Baracod\Larastarterkit\Generator\DefinitionFile\Contracts\ArrayConvertible;
use Baracod\Larastarterkit\Generator\DefinitionFile\Value\BackendConfig;
use Baracod\Larastarterkit\Generator\DefinitionFile\Value\FrontendConfig;

/**
 * Class ModelDefinition
 *
 * Représente un modèle du fichier de définitions (métadonnées + fillables + config).
 *
 * @phpstan-type ModelArray array{
 *   name: string,
 *   key: string,
 *   namespace: string,
 *   tableName: string,
 *   moduleName: string,
 *   fillable: array<int, array{name:string,type:string,defaultValue?:mixed,customizedType?:string|null}>,
 *   relations: array<int, mixed>,
 *   path?: string|null,
 *   fqcn?: string|null,
 *   backend: array<string, mixed>,
 *   frontend: array<string, mixed>
 * }
 *
 * @example
 * $model = ModelDefinition::new('blog-author', 'BlogAuthor', 'Modules\\Blog\\Models', 'blog_authors', 'Blog');
 * $model->addField(FieldDefinition::string('name'));
 * $model->backend()->hasController(true)->hasRoute(true);
 */
final class ModelDefinition implements ArrayConvertible
{
    /** @var array<string, FieldDefinition> */
    private array $fillable = [];

    /** @var array<int, mixed> */
    private array $relations = [];

    private BackendConfig $backend;

    private FrontendConfig $frontend;

    /**
     * @throws DomainException Si une propriété requise est vide
     */
    private function __construct(
        private string $key,
        private string $name,
        private string $namespace,
        private string $tableName,
        private string $moduleName,
        private ?string $path = null,
        private ?string $fqcn = null,
    ) {
        self::assertNonEmpty($key, 'key');
        self::assertNonEmpty($name, 'name');
        self::assertNonEmpty($namespace, 'namespace');
        self::assertNonEmpty($tableName, 'tableName');
        self::assertNonEmpty($moduleName, 'moduleName');

        $this->backend = new BackendConfig;
        $this->frontend = new FrontendConfig;
    }

    /**
     * Fabrique un modèle typé.
     *
     * @return static
     */
    public static function new(
        string $key,
        string $name,
        string $namespace,
        string $tableName,
        string $moduleName
    ): self {
        return new self($key, $name, $namespace, $tableName, $moduleName);
    }

    /**
     * Construit un modèle à partir d’un tableau associatif (décodage JSON).
     *
     * @param  array<string, mixed>  $a
     * @return static
     */
    public static function fromArray(array $a): self
    {
        $m = new self(
            key: (string) ($a['key'] ?? ''),
            name: (string) ($a['name'] ?? ''),
            namespace: (string) ($a['namespace'] ?? ''),
            tableName: (string) ($a['tableName'] ?? ''),
            moduleName: (string) ($a['moduleName'] ?? ($a['module'] ?? '')),
            path: isset($a['path']) ? (string) $a['path'] : null,
            fqcn: isset($a['fqcn']) ? (string) $a['fqcn'] : null,
        );

        foreach ((array) ($a['fillable'] ?? []) as $f) {
            $field = FieldDefinition::fromArray($f);
            $m->fillable[$field->name] = $field;
        }

        $m->relations = is_array($a['relations'] ?? null) ? $a['relations'] : [];
        $m->backend = BackendConfig::fromArray((array) ($a['backend'] ?? []));
        $m->frontend = FrontendConfig::fromArray((array) ($a['frontend'] ?? []));

        return $m;
    }

    /**
     * Retourne la clé unique du modèle (ex: "blog-author").
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Nom de classe sans namespace (ex: "BlogAuthor").
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Namespace PHP du modèle (ex: "Modules\Blog\Models").
     */
    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * Nom de la table SQL (ex: "blog_authors").
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Nom du module (ex: "Blog").
     */
    public function moduleName(): string
    {
        return $this->moduleName;
    }

    /**
     * Chemin du fichier du modèle Laravel physique, si connu.
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * FQCN complet (ex: "Modules\Blog\Models\BlogAuthor"), si connu.
     */
    public function fqcn(): ?string
    {
        return $this->fqcn;
    }

    /**
     * Accès à la configuration backend (objet mutable fluent).
     */
    public function backend(): BackendConfig
    {
        return $this->backend;
    }

    /**
     * Accès à la configuration frontend (objet mutable fluent).
     */
    public function frontend(?FrontendConfig $frontend = null): FrontendConfig
    {
        if (empty($frontend)) {
            return $this->frontend;
        }

        return $this->frontend;
    }

    /**
     * Ajoute un champ "fillable".
     *
     * @return $this
     *
     * @throws DomainException Si un champ avec le même nom existe déjà
     */
    public function addField(FieldDefinition $field): self
    {
        if (isset($this->fillable[$field->name])) {
            throw new \DomainException("Champ déjà existant: {$field->name}");
        }

        $this->fillable[$field->name] = $field;

        return $this;
    }

    /**
     * Ajoute ou remplace un champ "fillable".
     *
     * @return $this
     */
    public function upsertField(FieldDefinition $field): self
    {
        $this->fillable[$field->name] = $field;

        return $this;
    }

    /**
     * Supprime un champ "fillable" s’il existe.
     *
     * @param  string  $name  Nom du champ
     * @return $this
     */
    public function removeField(string $name): self
    {
        unset($this->fillable[$name]);

        return $this;
    }

    /**
     * Indique si le modèle possède un champ donné.
     */
    public function hasField(string $name): bool
    {
        return isset($this->fillable[$name]);
    }

    /**
     * Retourne la liste des noms de champs "fillable".
     *
     * @return list<string>
     */
    public function fillableNames(): array
    {
        return array_values(array_keys($this->fillable));
    }

    /**
     * Retourne les définitions des champs "fillable".
     *
     * @return array<string, FieldDefinition> Clé = nom du champ
     */
    public function fields(): array
    {
        return $this->fillable;
    }

    /**
     * Retourne les relations (tableau libre, selon votre structure).
     *
     * @return array<int, mixed>
     */
    public function relations(): array
    {
        return $this->relations;
    }

    /**
     * Convertit le modèle en tableau conforme au schéma JSON.
     *
     * @return ModelArray
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'key' => $this->key,
            'namespace' => $this->namespace,
            'tableName' => $this->tableName,
            'moduleName' => $this->moduleName,
            'fillable' => array_values(array_map(
                static fn (FieldDefinition $f) => $f->toArray(),
                $this->fillable
            )),
            'relations' => $this->relations,
            'path' => $this->path,
            'fqcn' => $this->fqcn,
            'backend' => $this->backend->toArray(),
            'frontend' => $this->frontend->toArray(),
        ];
    }

    /**
     * Valide qu’une chaîne requise n’est pas vide.
     *
     *
     * @throws DomainException
     */
    private static function assertNonEmpty(string $v, string $field): void
    {
        if ($v === '') {
            throw new \DomainException("Le champ '{$field}' est requis et ne peut pas être vide.");
        }
    }
}
