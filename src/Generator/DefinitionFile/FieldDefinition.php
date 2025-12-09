<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile;

use Baracod\Larastarterkit\Generator\DefinitionFile\Contracts\ArrayConvertible;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;

/**
 * Class FieldDefinition
 *
 * Représente un champ "fillable" d’un modèle.
 *
 * @phpstan-type FieldArray array{
 *   name: string,
 *   type: string,
 *   defaultValue?: mixed,
 *   customizedType?: string|null
 * }
 *
 * @example
 * FieldDefinition::make('email', FieldType::String)->default(null);
 */
final class FieldDefinition implements ArrayConvertible
{
    /**
     * @param  string  $name  Nom du champ (snake_case recommandé)
     * @param  FieldType  $type  Type fort (enum)
     * @param  mixed  $defaultValue  Valeur par défaut si applicable
     * @param  string|null  $customizedType  Type personnalisé (si besoin)
     *
     * @throws DomainException Si le nom est invalide
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public mixed $defaultValue = null,
        public ?string $customizedType = '',
    ) {
        self::assertValidName($name);
    }

    /**
     * Fabrique un champ avec nom et type.
     *
     * @return static
     */
    public static function make(string $name, FieldType $type): self
    {
        return new self($name, $type);
    }

    /**
     * Raccourci pour un champ string.
     *
     * @return static
     */
    public static function string(string $name): self
    {
        return self::make($name, FieldType::String);
    }

    /**
     * Définit la valeur par défaut.
     *
     * @return $this
     */
    public function default(mixed $value): self
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Définit un type personnalisé (facultatif).
     *
     * @return $this
     */
    public function customized(?string $type): self
    {
        $this->customizedType = $type;

        return $this;
    }

    /**
     * Construit un champ depuis un tableau.
     *
     * @param  array<string, mixed>  $a
     * @return static
     *
     * @throws DomainException Si le nom est invalide ou type inconnu
     */
    public static function fromArray(array $a): self
    {
        $name = (string) ($a['name'] ?? '');
        $type = FieldType::from($a['type'] ?? 'string');

        return new self(
            name: $name,
            type: $type,
            defaultValue: $a['defaultValue'] ?? null,
            customizedType: $a['customizedType'] ?? '',
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return FieldArray
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'defaultValue' => $this->defaultValue,
            'customizedType' => $this->customizedType,
        ];
    }

    /**
     * Vérifie la validité d’un nom de champ.
     *
     *
     * @throws DomainException Si invalide
     */
    private static function assertValidName(string $name): void
    {
        if ($name === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \DomainException("Nom de champ invalide: '{$name}'");
        }
    }
}
