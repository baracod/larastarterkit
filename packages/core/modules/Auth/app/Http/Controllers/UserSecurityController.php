<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Auth\Http\Requests\ChangeUserPasswordRequest;
use Modules\Auth\Http\Requests\SendPasswordResetRequest;
use Modules\Auth\Services\PasswordService;
use RuntimeException;

class UserSecurityController extends Controller
{
    public function __construct(protected PasswordService $passwordService) {}

    public function changePassword(ChangeUserPasswordRequest $request, int $id): JsonResponse
    {
        if ($request->user()->id !== $id) {
            return ApiResponse::forbidden('Vous n\'êtes pas autorisé à modifier ce mot de passe.');
        }

        try {
            $this->passwordService->changePassword($request->user(), $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode() ?: 422);
        }

        return ApiResponse::success(null, 'Mot de passe mis à jour avec succès.');
    }

    public function forceChangePassword(SendPasswordResetRequest $request): JsonResponse
    {
        Gate::authorize('forcePasswordReset', \Modules\Auth\Models\User::class);

        $target = $this->passwordService->requestPasswordReset($request->user(), $request->validated());

        return ApiResponse::success(
            ['userId' => $target->id, 'mustChangePassword' => $target->must_change_password],
            'Lien de réinitialisation mis en file d’envoi.'
        );
    }
}
