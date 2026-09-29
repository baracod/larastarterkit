<?php

namespace Modules\Auth\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\User;

class UserSecurityService
{
    /**
     * @throws AuthorizationException
     */
    public function changePassword(User $authUser, int $targetUserId, string $currentPassword, string $newPassword): bool
    {
        if ($authUser->id !== $targetUserId) {
            throw new AuthorizationException('Vous n\'etes pas autorise a modifier ce mot de passe.');
        }

        $targetUser = User::query()->findOrFail($targetUserId);

        if (! Hash::check($currentPassword, (string) $targetUser->password)) {
            return false;
        }

        $targetUser->update([
            'password' => Hash::make($newPassword),
        ]);

        return true;
    }
}
