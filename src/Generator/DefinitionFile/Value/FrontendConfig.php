<?php

namespace Baracod\Larastarterkit\Generator\DefinitionFile\Value;

use Baracod\Larastarterkit\Generator\DefinitionFile\Contracts\ArrayConvertible;

/**
 * Class FrontendConfig
 *
 * Options de génération frontend pour un modèle donné.
 *
 * @phpstan-type FrontendArray array{
 *   hasType: bool,
 *   hasApi: bool,
 *   hasLang: bool,
 *   hasAddOrEditComponent: bool,
 *   hasReadComponent: bool,
 *   hasIndex: bool,
 *   hasMenu: bool,
 *   hasPermission: bool,
 *   fields: array<int, mixed>,
 *   casl: array{
 *     create: bool, read: bool, update: bool, delete: bool, access: bool
 *   }
 * }
 */
final class FrontendConfig implements ArrayConvertible
{
    /**
     * @param  array<int,mixed>  $fields
     */
    public function __construct(
        public bool $hasType = false,
        public bool $hasApi = false,
        public bool $hasLang = false,
        public bool $hasAddOrEditComponent = false,
        public bool $hasReadComponent = false,
        public bool $hasIndex = false,
        public bool $hasMenu = false,
        public bool $hasPermission = false,
        public array $fields = [],
        public CaslPermissions $casl = new CaslPermissions,
    ) {
        // Intentionnellement vide.
    }

    /**
     * Construit à partir d’un tableau associatif.
     *
     * @param  array<string, mixed>  $a
     * @return static
     */
    public static function fromArray(array $a): self
    {
        return new self(
            (bool) ($a['hasType'] ?? false),
            (bool) ($a['hasApi'] ?? false),
            (bool) ($a['hasLang'] ?? false),
            (bool) ($a['hasAddOrEditComponent'] ?? false),
            (bool) ($a['hasReadComponent'] ?? false),
            (bool) ($a['hasIndex'] ?? false),
            (bool) ($a['hasMenu'] ?? false),
            (bool) ($a['hasPermission'] ?? false),
            is_array($a['fields'] ?? null) ? $a['fields'] : [],
            CaslPermissions::fromArray($a['casl'] ?? []),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return FrontendArray
     */
    public function toArray(): array
    {
        return [
            'hasType' => $this->hasType,
            'hasApi' => $this->hasApi,
            'hasLang' => $this->hasLang,
            'hasAddOrEditComponent' => $this->hasAddOrEditComponent,
            'hasReadComponent' => $this->hasReadComponent,
            'hasIndex' => $this->hasIndex,
            'hasMenu' => $this->hasMenu,
            'hasPermission' => $this->hasPermission,
            'fields' => $this->fields,
            'casl' => $this->casl->toArray(),
        ];
    }

    /**
     * Raccourci fluide pour activer/désactiver la génération du type TS.
     *
     * @return $this
     */
    public function hasType(bool $value): self
    {
        $this->hasType = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver la génération de l’API front.
     *
     * @return $this
     */
    public function hasApi(bool $value): self
    {
        $this->hasApi = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver les fichiers de langue (i18n).
     *
     * @return $this
     */
    public function hasLang(bool $value): self
    {
        $this->hasLang = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver le composant AddOrEdit.
     *
     * @return $this
     */
    public function hasAddOrEditComponent(bool $value): self
    {
        $this->hasAddOrEditComponent = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver le composant Read (détail).
     *
     * @return $this
     */
    public function hasReadComponent(bool $value): self
    {
        $this->hasReadComponent = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver la page Index.
     *
     * @return $this
     */
    public function hasIndex(bool $value): self
    {
        $this->hasIndex = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver l’ajout au menu.
     *
     * @return $this
     */
    public function hasMenu(bool $value): self
    {
        $this->hasMenu = $value;

        return $this;
    }

    /**
     * Raccourci fluide pour activer/désactiver la génération des permissions.
     *
     * @return $this
     */
    public function hasPermission(bool $value): self
    {
        $this->hasPermission = $value;

        return $this;
    }

    /**
     * Définit la liste des champs front (métadonnées côté UI).
     *
     * @param  array<int,mixed>  $fields
     * @return $this
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Définit les permissions CASL (objet ou tableau).
     *
     * @param  CaslPermissions|array<string,bool>  $casl
     * @return $this
     */
    public function casl(CaslPermissions|array $casl): self
    {
        $this->casl = is_array($casl) ? CaslPermissions::fromArray($casl) : $casl;

        return $this;
    }
}
