<?php

namespace Baracod\Larastarterkit\Core\Services;

use Baracod\Larastarterkit\Core\Models\User;
use PhpMcp\Server\Attributes\McpTool;
use PhpMcp\Server\Attributes\Schema;

class UserTools
{
    #[McpTool(name: 'users.list', description: 'Lister les utilisateurs avec recherche et pagination')]
    public function list(
        #[Schema(type: 'string', nullable: true, description: 'Texte à rechercher dans name/email')]
        ?string $q = null,
        #[Schema(type: 'integer', minimum: 1)]
        int $page = 1,
        #[Schema(type: 'integer', minimum: 1, maximum: 100)]
        int $per_page = 25,
    ): array {
        $query = User::query();

        if ($q !== null && $q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($per_page, ['*'], 'page', $page);

        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'data' => collect($paginator->items())->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'created_at' => optional($u->created_at)->toIso8601String(),
                ];
            })->all(),
        ];
    }
}
