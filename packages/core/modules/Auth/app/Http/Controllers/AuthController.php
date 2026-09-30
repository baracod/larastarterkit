<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Baracod\Larastarterkit\Core\Http\Middleware\ConvertRequestToSnakeCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Http\Requests\ForgotPasswordRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\ResetPasswordRequest;
use Modules\Auth\Notifications\LoginAlert;
use Modules\Auth\Services\AbilityService;
use Modules\Auth\Services\PasswordService;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        protected PasswordService $passwordService,
        protected AbilityService $abilityService,
    ) {
        $this->middleware(ConvertRequestToSnakeCase::class);
        app()->setLocale('fr');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // Cookie authentication needs a session: Sanctum only starts one for stateful (first-party) origins.
        if (! $request->hasSession()) {
            Log::channel('auth')->warning('Login refused: request origin is not a Sanctum stateful domain', [
                'origin' => $request->headers->get('origin') ?? $request->headers->get('referer'),
                'ip' => $request->ip(),
            ]);

            return ApiResponse::error('Origine non autorisée pour la connexion. Ajoutez ce domaine à SANCTUM_STATEFUL_DOMAINS.', 419);
        }

        $credentials = $request->only(['email', 'password']);
        $rememberMe = $request->boolean('remember_me', false);

        if (! Auth::guard('web')->attempt($credentials, $rememberMe)) {
            Log::channel('auth')->warning('Failed login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return ApiResponse::unauthorized('Identifiants invalides');
        }

        $user = Auth::guard('web')->user()->load('roles');

        if (! $user->active) {
            Log::channel('auth')->warning('Inactive user login attempt', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ApiResponse::locked('Votre compte est désactivé. Contactez l\'administrateur.');
        }

        $request->session()->regenerate();

        $user->notify(new LoginAlert(now()->toIso8601String(), (string) $request->ip(), mb_substr((string) $request->userAgent(), 0, 500), $request->validated('email_locale')));

        $abilityRules = $this->abilityService->buildRulesForUser($user);
        $permissions = $user->permissions()->get();
        $roles = $user->roles()->get();

        Log::channel('auth')->info('Successful login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
        ]);

        return ApiResponse::success([
            'user' => $user->withoutRelations()->toArray(),
            'ability_rules' => $abilityRules,
            'permissions' => $permissions,
            'roles' => $roles,
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::success($request->user()->load('roles')->toArray());
    }

    public function logout(Request $request): JsonResponse
    {
        Log::channel('auth')->info('User logout', [
            'user_id' => $request->user()->id,
            'email' => $request->user()->email,
            'ip' => $request->ip(),
        ]);

        $accessToken = $request->user()->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(null, 'Successfully logged out.');
    }

    public function forgottenPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $this->passwordService->sendResetLink($request->validated());
        } catch (RuntimeException $e) {
            // Silently swallow — anti-enumeration
        }

        return ApiResponse::success(
            null,
            'Si votre email existe dans notre système, vous recevrez un lien de réinitialisation.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->passwordService->resetPassword($request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode() ?: 400);
        }

        return ApiResponse::success(null, 'Votre mot de passe a été réinitialisé avec succès.');
    }
}
