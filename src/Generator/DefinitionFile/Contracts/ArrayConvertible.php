<?php

namespace Baracod\Larastarterkit\Generator\DefinitionFile\Contracts;

/**
 * Interface ArrayConvertible
 *
 * Interface simple pour garantir qu’une entité peut être convertie
 * en tableau associatif, prêt à être sérialisé en JSON.
 *
 * @author  VotreNom
 */
interface ArrayConvertible
{
    /**
     * Convertit l’instance en tableau associatif.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
