<?php

namespace Modules\Auth\Policies;

use Modules\Auth\Models\User;

class UserPolicy
{
    /**
     * Only administrators can force a password reset on another user.
     */
    public function forcePasswordReset(User $authUser): bool
    {
        return $authUser->hasRole('administrator');
    }
}
