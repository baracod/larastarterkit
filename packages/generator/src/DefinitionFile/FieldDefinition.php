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
 *   customizedType?: string|null,
 *   nullable?: bool|null,
 *   unique?: bool,
 *   length?: int|null,
 *   enumValues?: list<string>,
 *   label?: string|null
 * }
 *
 * @example
 * FieldDefinition::make('email', FieldType::String)->unique()->nullable(false);
 */
final class FieldDefinition implements ArrayConvertible
{
    /** Noms techniques optionnels par défaut dans les anciennes définitions. */
    private const GENERATED_NAMES = ['id', 'uuid', 'hash'];

    /**
     * @param  string  $name  Nom du champ (snake_case recommandé)
     * @param  FieldType  $type  Type fort (enum)
     * @param  mixed  $defaultValue  Valeur par défaut si applicable
     * @param  string|null  $customizedType  Règles de validation personnalisées (remplacent le mapping du type)
     * @param  bool|null  $nullable  Null = ancien format : déduit de defaultValue (null ⇒ optionnel)
     * @param  list<string>  $enumValues  Valeurs autorisées pour FieldType::Enum
     *
     * @throws \DomainException Si le nom est invalide
     */
    public function __construct(
        public string $name,
        public FieldType $type,
        public mixed $defaultValue = null,
        public ?string $customizedType = '',
        public ?bool $nullable = null,
        public bool $unique = false,
        public ?int $length = null,
        public array $enumValues = [],
        public ?string $label = null,
    ) {
        self::assertValidName($name);
        $this->enumValues = array_values(array_unique(array_filter(array_map('strval', $enumValues), static fn (string $v) => $v !== '')));
    }

    /**
     * Fabrique un champ avec nom et type.
     */
    public static function make(string $name, FieldType $type): self
    {
        return new self($name, $type);
    }

    /**
     * Raccourci pour un champ string.
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
     * Définit des règles de validation personnalisées (facultatif).
     *
     * @return $this
     */
    public function customized(?string $type): self
    {
        $this->customizedType = $type;

        return $this;
    }

    /** @return $this */
    public function nullable(bool $nullable = true): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    /** @return $this */
    public function unique(bool $unique = true): self
    {
        $this->unique = $unique;

        return $this;
    }

    /** @return $this */
    public function length(?int $length): self
    {
        $this->length = $length;

        return $this;
    }

    /**
     * @param  list<string>  $values
     * @return $this
     */
    public function enum(array $values): self
    {
        $this->type = FieldType::Enum;
        $this->enumValues = array_values(array_unique(array_map('strval', $values)));

        return $this;
    }

    /**
     * Le champ accepte-t-il l'absence de valeur ?
     * Sans indication explicite, l'ancien format s'applique : une valeur par défaut nulle rend le champ optionnel.
     */
    public function isNullable(): bool
    {
        if ($this->nullable !== null) {
            return $this->nullable;
        }
        if (in_array(strtolower($this->name), self::GENERATED_NAMES, true)) {
            return true;
        }

        return $this->defaultValue === null || (is_string($this->defaultValue) && strtoupper(trim($this->defaultValue)) === 'NULL');
    }

    /**
     * Valeur par défaut exploitable (la chaîne "NULL" héritée des imports SQL vaut null).
     */
    public function effectiveDefault(): mixed
    {
        return is_string($this->defaultValue) && strtoupper(trim($this->defaultValue)) === 'NULL' ? null : $this->defaultValue;
    }

    /**
     * Construit un champ depuis un tableau.
     *
     * @param  array<string, mixed>  $a
     *
     * @throws \DomainException Si le nom est invalide ou le type inconnu
     */
    public static function fromArray(array $a): self
    {
        $type = FieldType::tryFrom((string) ($a['type'] ?? 'string'));
        if ($type === null) {
            throw new \DomainException("Type de champ inconnu: '{$a['type']}'");
        }

        return new self(
            name: (string) ($a['name'] ?? ''),
            type: $type,
            defaultValue: $a['defaultValue'] ?? null,
            customizedType: $a['customizedType'] ?? '',
            nullable: array_key_exists('nullable', $a) && $a['nullable'] !== null ? (bool) $a['nullable'] : null,
            unique: (bool) ($a['unique'] ?? false),
            length: isset($a['length']) && is_numeric($a['length']) ? (int) $a['length'] : null,
            enumValues: is_array($a['enumValues'] ?? null) ? $a['enumValues'] : [],
            label: isset($a['label']) && $a['label'] !== '' ? (string) $a['label'] : null,
        );
    }

    /**
     * Les attributs facultatifs ne sont écrits que s'ils sont renseignés : les fichiers existants restent stables.
     *
     * @return FieldArray
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'defaultValue' => $this->defaultValue,
            'customizedType' => $this->customizedType,
            'nullable' => $this->nullable,
            'unique' => $this->unique ?: null,
            'length' => $this->length,
            'enumValues' => $this->enumValues ?: null,
            'label' => $this->label,
        ], static fn ($value, $key) => in_array($key, ['name', 'type', 'defaultValue', 'customizedType'], true) || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Vérifie la validité d’un nom de champ.
     *
     * @throws \DomainException Si invalide
     */
    private static function assertValidName(string $name): void
    {
        if ($name === '' || ! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \DomainException("Nom de champ invalide: '{$name}'");
        }
    }
}
