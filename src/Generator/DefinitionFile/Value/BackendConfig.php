<?php

namespace Baracod\Larastarterkit\Generator\DefinitionFile\Value;

use Baracod\Larastarterkit\Generator\DefinitionFile\Contracts\ArrayConvertible;

/**
 * Class BackendConfig
 *
 * Options de génération backend pour un modèle donné.
 *
 * @phpstan-type BackendArray array{
 *   hasModel: bool,
 *   hasController: bool,
 *   hasRequest: bool,
 *   hasRoute: bool,
 *   hasPermission: bool,
 *   apiRoute?: string|null
 * }
 */
final class BackendConfig implements ArrayConvertible
{
    /**
     * Indique si le modèle Eloquent doit être généré.
     */
    public bool $hasModel;

    /**
     * Indique si le contrôleur doit être généré.
     */
    public bool $hasController;

    /**
     * Indique si la Request (FormRequest) doit être générée.
     */
    public bool $hasRequest;

    /**
     * Indique si la route doit être déclarée.
     */
    public bool $hasRoute;

    /**
     * Indique si les permissions backend doivent être générées.
     */
    public bool $hasPermission;

    /**
     * Chemin de la route d'API (ex: "api/v1/blog/blog-authors").
     */
    public ?string $apiRoute;

    public function __construct(
        bool $hasModel = false,
        bool $hasController = false,
        bool $hasRequest = false,
        bool $hasRoute = false,
        bool $hasPermission = false,
        ?string $apiRoute = null,
    ) {
        $this->hasModel = $hasModel;
        $this->hasController = $hasController;
        $this->hasRequest = $hasRequest;
        $this->hasRoute = $hasRoute;
        $this->hasPermission = $hasPermission;
        $this->apiRoute = $apiRoute;
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
            (bool) ($a['hasModel'] ?? false),
            (bool) ($a['hasController'] ?? false),
            (bool) ($a['hasRequest'] ?? false),
            (bool) ($a['hasRoute'] ?? false),
            (bool) ($a['hasPermission'] ?? false),
            isset($a['apiRoute']) ? (string) $a['apiRoute'] : null,
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return BackendArray
     */
    public function toArray(): array
    {
        return [
            'hasModel' => $this->hasModel,
            'hasController' => $this->hasController,
            'hasRequest' => $this->hasRequest,
            'hasRoute' => $this->hasRoute,
            'hasPermission' => $this->hasPermission,
            'apiRoute' => $this->apiRoute,
        ];
    }

    /**
     * Active/désactive la génération du modèle Eloquent.
     *
     * @return $this
     */
    public function hasModel(bool $value): self
    {
        $this->hasModel = $value;

        return $this;
    }

    /**
     * Active/désactive la présence d’un contrôleur.
     *
     * @return $this
     */
    public function hasController(bool $value): self
    {
        $this->hasController = $value;

        return $this;
    }

    /**
     * Active/désactive la génération d’une FormRequest.
     *
     * @return $this
     */
    public function hasRequest(bool $value): self
    {
        $this->hasRequest = $value;

        return $this;
    }

    /**
     * Active/désactive la déclaration de route.
     *
     * @return $this
     */
    public function hasRoute(bool $value): self
    {
        $this->hasRoute = $value;

        return $this;
    }

    /**
     * Active/désactive la génération des permissions backend.
     *
     * @return $this
     */
    public function hasPermission(bool $value): self
    {
        $this->hasPermission = $value;

        return $this;
    }

    /**
     * Définit rapidement la route d’API.
     *
     * @return $this
     *
     * @example
     * $backend->withApiRoute('api/commerce/products');
     */
    public function withApiRoute(string $apiRoute): self
    {
        $this->apiRoute = $apiRoute;

        return $this;
    }

    /**
     * Supprime la route d’API (met à null).
     *
     * @return $this
     */
    public function clearApiRoute(): self
    {
        $this->apiRoute = null;

        return $this;
    }
}
