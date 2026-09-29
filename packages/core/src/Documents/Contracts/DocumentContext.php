<?php

namespace Baracod\Larastarterkit\Core\Documents\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

interface DocumentContext
{
    public function resolve(int $id): Model;

    public function authorize(User $user, Model $record, string $action): bool;

    /** @return array<string, array{title: string, view: string, version: string, fields: array, signatories: array}> */
    public function documents(): array;

    /** @return array{reference: string, sections: array} */
    public function context(Model $record): array;
}
