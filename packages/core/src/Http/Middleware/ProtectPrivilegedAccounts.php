<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Baracod\Larastarterkit\Core\Helpers\CaseConvert;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectPrivilegedAccounts
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->is('api/v1/auth/users*') || $request->user()?->hasRole('administrator')) {
            return $next($request);
        }

        $target = $request->route('user') ?? $request->route('id');
        $input = CaseConvert::toSnake($request->except(array_keys($request->allFiles())));
        $ids = $input['user_ids'] ?? [];

        if ($request->is('api/v1/auth/users/delete-multiple')) {
            $ids = $request->all();
        }

        abort_unless(is_array($ids), 422);

        if ($target !== null) {
            $ids[] = $target instanceof \Modules\Auth\Models\User ? $target->getKey() : $target;
        }

        foreach ($ids as $id) {
            abort_unless(is_scalar($id) && ctype_digit((string) $id), 422);
        }

        $targetsAdministrator = \Modules\Auth\Models\User::query()
            ->whereKey($ids)
            ->whereHas('roles', fn ($query) => $query->where('name', 'administrator'))
            ->exists();

        abort_if($targetsAdministrator, 403, 'La gestion des comptes administrateurs est réservée aux administrateurs.');

        return $next($request);
    }
}
