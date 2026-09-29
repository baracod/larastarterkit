<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai;

/**
 * Résultat d'une tâche assistée : la valeur et sa provenance (IA ou règle déterministe).
 *
 * @template T
 */
final class AiOutcome
{
    /**
     * @param  T  $value
     */
    public function __construct(
        public readonly mixed $value,
        public readonly bool $fromAi,
        public readonly ?string $reason = null,
    ) {}

    public function source(): string
    {
        return $this->fromAi ? 'IA' : 'règles';
    }
}
