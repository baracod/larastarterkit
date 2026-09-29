<?php

use Baracod\Larastarterkit\Core\Models\User;
use PhpMcp\Laravel\Facades\Mcp;

Mcp::tool(function (?string $q = null, int $page = 1, int $per_page = 25, ?string $sort = 'id', ?string $dir = 'desc'): array {
    $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
    $allowedSorts = ['id', 'name', 'email', 'created_at'];
    $sort = in_array($sort, $allowedSorts, true) ? $sort : 'id';

    $query = User::query();

    if ($q !== null && $q !== '') {
        $query->where(function ($w) use ($q) {
            $w->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%");
        });
    }

    $p = $query->orderBy($sort, $dir)->paginate(
        max(1, min($per_page, 100)), // clamp 1..100
        ['*'],
        'page',
        max(1, $page)
    );

    return [
        'total' => $p->total(),
        'page' => $p->currentPage(),
        'per_page' => $p->perPage(),
        'sort' => $sort,
        'dir' => $dir,
        'data' => collect($p->items())->map(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'created_at' => optional($u->created_at)->toIso8601String(),
        ])->all(),
    ];
})
    ->name('users.list')
    ->description('Lister les utilisateurs (recherche + pagination + tri)');

Mcp::tool(function (int $id): array {
    $user = User::find($id);
    if (! $user) {
        return [
            'error' => 'Utilisateur non trouvé',
            'id' => $id,
        ];
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'created_at' => optional($user->created_at)->toIso8601String(),
        'updated_at' => optional($user->updated_at)->toIso8601String(),
        // Ajoutez ici d'autres champs si besoin
    ];
})
    ->name('users.profile')
    ->description('Afficher le profil complet d\'un utilisateur par son ID');
