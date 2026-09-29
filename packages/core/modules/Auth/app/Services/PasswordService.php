<?php

namespace Modules\Auth\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Auth\Models\User;
use Modules\Auth\Notifications\PasswordChanged;
use Modules\Auth\Notifications\ResetPasswordLink;
use RuntimeException;

class PasswordService
{
    /**
     * Send a password reset link to the given email.
     *
     * @param  array{email: string}  $data
     */
    public function sendResetLink(array $data): void
    {
        $email = $data['email'];

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        DB::table('auth_password_reset_tokens')
            ->where('email', $email)
            ->delete();

        $token = Str::random(64);

        DB::table('auth_password_reset_tokens')->insert([
            'email' => $email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $user->notify(new ResetPasswordLink($token, $data['email_locale'] ?? null));

        Log::channel('auth')->info('Password reset link sent', [
            'email' => $email,
        ]);
    }

    /**
     * Reset a user password via a valid token.
     *
     * @param  array{email: string, token: string, new_password: string}  $data
     */
    public function resetPassword(array $data): void
    {
        $tokenData = DB::table('auth_password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $tokenData || ! Hash::check($data['token'], $tokenData->token)) {
            Log::channel('auth')->warning('Invalid password reset token', ['email' => $data['email']]);
            throw new RuntimeException('Token invalide ou expiré.', 400);
        }

        if (Carbon::parse($tokenData->created_at)->addHour()->isPast()) {
            DB::table('auth_password_reset_tokens')->where('email', $data['email'])->delete();
            Log::channel('auth')->warning('Expired password reset token', ['email' => $data['email']]);
            throw new RuntimeException('Token expiré. Veuillez demander un nouveau lien.', 400);
        }

        $user = User::query()->where('email', $data['email'])->firstOrFail();

        $user->update([
            'password' => Hash::make($data['new_password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        $user->tokens()->delete();

        DB::table('auth_password_reset_tokens')->where('email', $data['email'])->delete();

        Log::channel('auth')->info('Password reset successfully', ['user_id' => $user->id]);
        $user->notify(new PasswordChanged(now()->toIso8601String(), locale: $data['email_locale'] ?? null));
    }

    /**
     * Change password for the authenticated user (requires current password).
     *
     * @param  array{current_password: string, new_password: string}  $data
     */
    public function changePassword(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], (string) $user->password)) {
            throw new RuntimeException('Le mot de passe actuel est incorrect.', 422);
        }

        $user->update([
            'password' => Hash::make($data['new_password']),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        Log::channel('auth')->info('User changed own password', ['user_id' => $user->id]);
        $user->notify(new PasswordChanged(now()->toIso8601String(), locale: $data['email_locale'] ?? null));
    }

    public function requestPasswordReset(User $admin, array $data): User
    {
        return DB::transaction(function () use ($admin, $data) {
            $target = User::query()->lockForUpdate()->findOrFail($data['user_id']);
            $target->update(['must_change_password' => true]);
            $target->tokens()->delete();
            $this->sendResetLink([
                'email' => $target->email,
                'email_locale' => $data['email_locale'] ?? null,
            ]);
            Log::channel('auth')->info('Admin requested password reset by email', [
                'admin_id' => $admin->id,
                'target_id' => $target->id,
            ]);

            return $target->fresh();
        });
    }

    /**
     * Force a password change for a target user (admin action, no current password needed).
     *
     * @param  array{user_id: int, new_password: string, must_change_password?: bool}  $data
     */
    public function forceChangePassword(User $admin, array $data): User
    {
        $target = User::query()->findOrFail($data['user_id']);

        $mustChange = (bool) ($data['must_change_password'] ?? false);

        $target->update([
            'password' => Hash::make($data['new_password']),
            'must_change_password' => $mustChange,
            'password_changed_at' => $mustChange ? null : now(),
        ]);

        $target->tokens()->delete();

        $target->notify(new PasswordChanged(now()->toIso8601String(), true, $mustChange, $data['email_locale'] ?? null));

        Log::channel('auth')->info('Admin forced password change', [
            'admin_id' => $admin->id,
            'target_id' => $target->id,
            'must_change' => $mustChange,
        ]);

        return $target->fresh();
    }
}
