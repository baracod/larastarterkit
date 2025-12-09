<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile;

use InvalidArgumentException;

/**
 * Class DefinitionQuery
 *
 * Petit "query builder" pour filtrer des {@see ModelDefinition}
 * sur base de leur représentation tableau (dot-notation simple).
 *
 * @example
 * $q = new DefinitionQuery($module->all());
 * $list = $q->where('backend.hasController', true)->get();
 */
final class DefinitionQuery
{
    /** @var array<string, ModelDefinition> */
    private array $models;

    /** @var callable(ModelDefinition):bool */
    private $predicate;

    /**
     * @param  array<string, ModelDefinition>  $models
     */
    public function __construct(array $models)
    {
        $this->models = $models;
        $this->predicate = static fn (ModelDefinition $m): bool => true;
    }

    /**
     * Filtre sur une clé en dot-notation (ex: "backend.hasController").
     *
     * Opérateurs : '=', '==', '!=', '<>', 'like'
     * - 'like' : utilise % en wildcard (ex: '%Author%')
     *
     * @param  string  $path  Clé dot-notation
     * @param  mixed  $operatorOrValue  Opérateur ou valeur si '=' implicite
     * @param  mixed|null  $maybeValue  Valeur si opérateur explicite
     * @return $this
     *
     * @throws InvalidArgumentException Si opérateur non supporté
     */
    public function where(string $path, mixed $operatorOrValue, mixed $maybeValue = null): self
    {
        $operator = $maybeValue === null ? '=' : (string) $operatorOrValue;
        $value = $maybeValue === null ? $operatorOrValue : $maybeValue;

        $prev = $this->predicate;

        $this->predicate = function (ModelDefinition $m) use ($prev, $path, $operator, $value): bool {
            $current = $this->getDot($m->toArray(), $path);

            $ok = match ($operator) {
                '=', '==' => $current == $value,
                '!=', '<>' => $current != $value,
                'like' => is_string($current) && is_string($value)
                    ? $this->like($current, $value)
                    : false,
                default => throw new InvalidArgumentException("Opérateur where() non supporté: {$operator}"),
            };

            return $prev($m) && $ok;
        };

        return $this;
    }

    /**
     * Retourne le premier modèle correspondant ou null.
     */
    public function first(): ?ModelDefinition
    {
        foreach ($this->models as $m) {
            if (($this->predicate)($m)) {
                return $m;
            }
        }

        return null;
    }

    /**
     * Retourne la liste des modèles correspondants.
     *
     * @return list<ModelDefinition>
     */
    public function get(): array
    {
        $out = [];
        foreach ($this->models as $m) {
            if (($this->predicate)($m)) {
                $out[] = $m;
            }
        }

        return $out;
    }

    /**
     * Vérifie la correspondance d’une chaîne à un motif %like%.
     */
    private function like(string $haystack, string $pattern): bool
    {
        $regex = '/^'.str_replace('%', '.*', preg_quote($pattern, '/')).'$/i';

        return (bool) preg_match($regex, $haystack);
    }

    /**
     * Récupère une valeur via dot-notation simple.
     *
     * @param  array<string, mixed>  $arr
     */
    private function getDot(array $arr, string $path): mixed
    {
        $cursor = $arr;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($cursor) || ! array_key_exists($seg, $cursor)) {
                return null;
            }
            $cursor = $cursor[$seg];
        }

        return $cursor;
    }
}
