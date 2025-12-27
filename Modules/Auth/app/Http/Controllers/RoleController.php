<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Auth\Http\Requests\RoleRequest;
use Modules\Auth\Models\Permission;
use Modules\Auth\Models\Role;

class RoleController
{
    public function index()
    {
        return Role::withCount('users')
            ->withCount('permissions')
            ->get();
    }

    public function show($id)
    {
        return Role::findOrFail($id);
    }

    public function store(RoleRequest $request)
    {
        $validated = $request->validated();

        return Role::create($validated);
    }

    public function update(RoleRequest $request, Role $role)
    {
        $validated = $request->validated();
        $role->update($validated);

        return $role;
    }

    public function destroy($id)
    {
        $model = Role::findOrFail($id);

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->all();

        $model = Role::whereIn('id', $ids);

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function getPermissions(int $id, Request $request)
    {
        $role = Role::findOrFail($id);

        return $role->permissions;
    }

    public function getCommonPermissions($roleIds, Request $request)
    {
        $roleIds = explode(',', $roleIds);

        $permissionIds = DB::table('auth_role_permissions')
            ->select('permission_id')
            ->whereIn('role_id', $roleIds)
            ->groupBy('permission_id')
            ->havingRaw('COUNT(DISTINCT role_id) = ?', [count($roleIds)]) // ne garder que les permissions qui apparaissent pour tous les rôles donnés.
            ->pluck('permission_id');

        $commonPermissions = Permission::whereIn('id', $permissionIds)->get();
        $common = Permission::whereIn('id', $permissionIds)->get();

        return $common;
        // return $roles->flatMap(fn($role) => $role->permissions);
    }

    public function attachPermissions(Request $request, string $ids)
    {
        // 1) Validation stricte des entrées
        $validated = $request->validate([
            'permissionIds' => ['required', 'array'],
            'permissionIds.*' => ['integer', Rule::exists('auth_permissions', 'id')],
        ]);

        $user = $request->user();

        if (! $user || ! $user->can('attach', 'auth_permissions')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2) Parsing des rôles (CSV → array unique, int)
        $roleIds = collect(explode(',', $ids))
            ->filter(fn ($v) => $v !== '' && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($roleIds->isEmpty()) {
            return response()->json(['message' => 'Aucun rôle valide fourni.'], 422);
        }

        // 3) Récupération des rôles existants
        $roles = Role::query()->whereIn('id', $roleIds)->get();
        if ($roles->isEmpty()) {
            return response()->json(['message' => 'Aucun rôle trouvé.'], 404);
        }

        // 4) Permissions (IDs uniques)
        $permissionIds = collect($validated['permissionIds'])->unique()->values();

        $isSingleRole = $roles->count() === 1;

        // 5) Exécution transactionnelle
        $result = DB::transaction(function () use ($roles, $permissionIds, $isSingleRole) {
            $summary = [
                'mode' => $isSingleRole ? 'sync' : 'attach_only',
                'roles' => [],
            ];

            if ($isSingleRole) {
                // Cas 1 : un seul rôle → SYNC (détache tout ce qui n’est pas dans la liste, attache le reste)
                /** @var \App\Models\Role $role */
                $role = $roles->first();

                // sync() retourne ['attached' => [], 'detached' => [], 'updated' => []]
                $changes = $role->permissions()->sync($permissionIds->all());

                $summary['roles'][] = [
                    'role_id' => $role->id,
                    'attached' => $changes['attached'] ?? [],
                    'detached' => $changes['detached'] ?? [],
                    'updated' => $changes['updated'] ?? [],
                ];
            } else {
                // Cas 2 : plusieurs rôles → AJOUT SANS DÉTACHEMENT
                foreach ($roles as $role) {
                    // Pour info: syncWithoutDetaching() n’enlève rien et ajoute ce qui manque
                    // On calcule ce qui va être ajouté pour le retour
                    $current = $role->permissions()->pluck('auth_permissions.id')->all();
                    $toAttach = array_values(array_diff($permissionIds->all(), $current));

                    if (! empty($toAttach)) {
                        $role->permissions()->syncWithoutDetaching($toAttach);
                    }

                    $summary['roles'][] = [
                        'role_id' => $role->id,
                        'attached' => $toAttach,
                        'detached' => [], // jamais détaché dans ce mode
                        'updated' => [],
                    ];
                }
            }

            return $summary;
        });

        return response()->json([
            'message' => $isSingleRole
                ? 'Permissions synchronisées pour le rôle.'
                : 'Permissions ajoutées aux rôles (aucun détachement effectué).',
            'data' => $result,
        ]);
    }

    public function detachPermissions(Request $request, string $ids)
    {
        // 1) Validation stricte des entrées
        $validated = $request->validate([
            'permissionIds' => ['required', 'array'],
            'permissionIds.*' => ['integer', Rule::exists('auth_permissions', 'id')],
        ]);

        $user = $request->user();

        if (! $user || ! $user->can('attach', 'auth_permissions')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2) Parsing des rôles (CSV → array unique, int)
        $roleIds = collect(explode(',', $ids))
            ->filter(fn ($v) => $v !== '' && is_numeric($v))
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        if ($roleIds->isEmpty()) {
            return response()->json(['message' => 'Aucun rôle valide fourni.'], 422);
        }

        // 3) Récupération des rôles existants
        $roles = Role::query()->whereIn('id', $roleIds)->get();
        if ($roles->isEmpty()) {
            return response()->json(['message' => 'Aucun rôle trouvé.'], 404);
        }

        // 4) Permissions (IDs uniques)
        $permissionIds = collect($validated['permissionIds'])->unique()->values();

        $isSingleRole = $roles->count() === 1;

        // 5) Exécution transactionnelle
        $result = DB::transaction(function () use ($roles, $permissionIds, $isSingleRole) {
            $summary = [
                'mode' => $isSingleRole ? 'sync' : 'attach_only',
                'roles' => [],
            ];

            if ($isSingleRole) {
                // Cas 1 : un seul rôle → SYNC (détache tout ce qui n’est pas dans la liste, attache le reste)
                /** @var \App\Models\Role $role */
                $role = $roles->first();

                // sync() retourne ['attached' => [], 'detached' => [], 'updated' => []]
                $changes = $role->permissions()->detach($permissionIds->all());

                $summary['roles'][] = [
                    'role_id' => $role->id,
                    'attached' => $changes['attached'] ?? [],
                    'detached' => $changes['detached'] ?? [],
                    'updated' => $changes['updated'] ?? [],
                ];
            } else {
                // Cas 2 : plusieurs rôles → AJOUT SANS DÉTACHEMENT
                foreach ($roles as $role) {

                    $res = $role->permissions()->detach($permissionIds->all());

                    $summary['roles'][] = [
                        'role_id' => $role->id,
                        'detached' => $res,
                        'updated' => [],
                    ];
                }
            }

            return $summary;
        });

        return response()->json([
            'message' => $isSingleRole
                ? 'Permissions synchronisées pour le rôle.'
                : 'Permissions ajoutées aux rôles (aucun détachement effectué).',
            'data' => $result,
        ]);
    }
}
