<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile\Value;

use Baracod\Larastarterkit\Generator\DefinitionFile\Contracts\ArrayConvertible;

/**
 * Class CaslPermissions
 *
 * Représente les permissions CASL côté front.
 *
 * @phpstan-type CaslArray array{
 *   create: bool,
 *   read: bool,
 *   update: bool,
 *   delete: bool,
 *   access: bool
 * }
 */
final class CaslPermissions implements ArrayConvertible
{
    /**
     * @param  bool  $create  Autorise la création
     * @param  bool  $read  Autorise la lecture
     * @param  bool  $update  Autorise la mise à jour
     * @param  bool  $delete  Autorise la suppression
     * @param  bool  $access  Autorise l’accès global
     */
    public function __construct(
        public bool $create = false,
        public bool $read = false,
        public bool $update = false,
        public bool $delete = false,
        public bool $access = false,
    ) {
        //
    }

    /**
     * Construit l’objet à partir d’un tableau.
     *
     * @param  array<string, mixed>  $a
     * @return static
     */
    public static function fromArray(array $a): self
    {
        return new self(
            (bool) ($a['create'] ?? false),
            (bool) ($a['read'] ?? false),
            (bool) ($a['update'] ?? false),
            (bool) ($a['delete'] ?? false),
            (bool) ($a['access'] ?? false),
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return CaslArray
     */
    public function toArray(): array
    {
        return [
            'create' => $this->create,
            'read' => $this->read,
            'update' => $this->update,
            'delete' => $this->delete,
            'access' => $this->access,
        ];
    }
}
