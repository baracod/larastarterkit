<?php

namespace Baracod\Larastarterkit\Core\Documents\Services;

use Illuminate\Database\Eloquent\Model;

class ProcedureDocumentService
{
    public function __construct(private readonly DocumentRegistry $registry) {}

    public function resolve(string $type, int $id, string $action): Model
    {
        $context = $this->registry->get($type);
        $record = $context->resolve($id);
        abort_unless(auth()->user() && $context->authorize(auth()->user(), $record, $action), 403);

        return $record;
    }

    public function canEdit(string $type, Model $record): bool
    {
        return $this->registry->get($type)->authorize(auth()->user(), $record, 'edit');
    }

    public function requirements(string $type): array
    {
        return array_map(fn (array $definition): string => $definition['title'], $this->registry->get($type)->documents());
    }
}
