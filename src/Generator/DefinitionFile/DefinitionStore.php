<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile;

use DomainException;

use function file_get_contents;
use function file_put_contents;
use function is_file;
use function json_decode;
use function json_encode;

/**
 * Class DefinitionStore
 *
 * Point d’entrée pour charger/persister un fichier de définitions de module.
 * Fournit un accès au {@see ModuleDefinition} et un petit query builder.
 *
 * @example
 * $store  = DefinitionStore::fromFile(base_path('module.json'));
 * $module = $store->module();
 *
 * $author = $module->model('blog-author');
 * $author->backend()->hasController(true);
 *
 * $list = $store->models()->where('backend.hasController', true)->get();
 * $store->save(); // persiste les modifications
 */
final class DefinitionStore
{
    /**
     * Chemin d’origine du fichier JSON (si chargé depuis un fichier).
     *
     * @var string|null
     */
    private ?string $filePath = null;

    /**
     * Module racine typé.
     *
     * @var ModuleDefinition
     */
    private ModuleDefinition $module;

    /**
     * @param ModuleDefinition $module
     * @param string|null      $filePath
     */
    private function __construct(ModuleDefinition $module, ?string $filePath)
    {
        $this->module   = $module;
        $this->filePath = $filePath;
    }

    /**
     * Construit un store depuis un tableau (décodage JSON déjà effectué).
     *
     * @param  array<string, mixed> $root
     * @return static
     */
    public static function fromArray(array $root): self
    {
        return new self(ModuleDefinition::fromArray($root), null);
    }

    /**
     * Construit un store depuis un fichier JSON.
     *
     * @param  string $path Chemin absolu/relatif vers le fichier JSON
     * @return static
     *
     * @throws DomainException Si le fichier est introuvable ou JSON invalide
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new DomainException("Fichier introuvable: {$path}");
        }

        $json = (string) file_get_contents($path);

        try {
            /** @var array<string, mixed> $root */
            $root = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DomainException("JSON invalide: {$e->getMessage()}", previous: $e);
        }

        return new self(ModuleDefinition::fromArray($root), $path);
    }

    /**
     * Accède au module racine.
     *
     * @return ModuleDefinition
     */
    public function module(): ModuleDefinition
    {
        return $this->module;
    }

    /**
     * Démarre une requête “façon Eloquent” sur les modèles.
     *
     * @return DefinitionQuery
     */
    public function models(): DefinitionQuery
    {
        return new DefinitionQuery($this->module->all());
    }

    /**
     * Sérialise et écrit le JSON sur disque.
     *
     * @param  string|null $override Chemin alternatif de sauvegarde
     * @return void
     *
     * @throws DomainException Si aucun chemin connu ou échec d’encodage
     */
    public function save(?string $override = null): void
    {
        $path = $override ?? $this->filePath;

        if (!$path) {
            throw new DomainException('Aucun chemin de sauvegarde connu.');
        }

        $json = json_encode(
            $this->module->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new DomainException('Échec d’encodage JSON.');
        }

        file_put_contents($path, $json);
    }
}
