<?php

namespace Modules\Auth\Services;

use Modules\Auth\Models\Permission;
use Modules\Auth\Models\User;

class AbilityService
{
    /**
     * Build the CASL ability rules for a given user.
     *
     * Includes:
     * - Permissions assigned via roles
     * - Permissions with always_allow = true (regardless of role)
     * - Permissions with is_public = true (accessible to all authenticated users)
     *
     * @return array<int, array{action: string, subject: string}>
     */
    public function buildRulesForUser(User $user): array
    {
        if ($user->hasRole('administrator')) {
            return [['action' => 'manage', 'subject' => 'all']];
        }

        $userPermissions = $user->permissions()
            ->get(['auth_permissions.action', 'auth_permissions.subject'])
            ->toBase()
            ->map(fn ($p) => ['action' => $p->action, 'subject' => $p->subject]);

        $alwaysAllowed = Permission::query()
            ->where(function ($q) {
                $q->where('always_allow', true)
                    ->orWhere('is_public', true);
            })
            ->get(['action', 'subject'])
            ->toBase()
            ->map(fn ($p) => ['action' => $p->action, 'subject' => $p->subject]);

        return $userPermissions
            ->merge($alwaysAllowed)
            ->unique(fn ($p) => $p['action'].'|'.$p['subject'])
            ->values()
            ->toArray();
    }
}
