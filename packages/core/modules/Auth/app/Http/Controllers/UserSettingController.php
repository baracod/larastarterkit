<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Services\SettingService;
use Modules\Auth\Http\Requests\UserSettingRequest;

class UserSettingController extends Controller
{
    public function __construct(protected SettingService $settingService) {}

    public function index(int $id): JsonResponse
    {
        $authUser = auth()->user();

        if ($authUser->id !== $id && ! $authUser->hasRole('administrator')) {
            return ApiResponse::forbidden('Vous n\'etes pas autorise a acceder a ces parametres.');
        }

        $settings = $this->settingService->getUserSettings($id);

        return ApiResponse::success($settings);
    }

    public function update(UserSettingRequest $request, int $id): JsonResponse
    {
        $authUser = auth()->user();

        if ($authUser->id !== $id && ! $authUser->hasRole('administrator')) {
            return ApiResponse::forbidden('Vous n\'etes pas autorise a modifier ces parametres.');
        }

        $data = $request->validated();
        $updated = [];

        foreach ($data['settings'] as $setting) {
            $updated[] = $this->settingService->set(
                key: $setting['key'],
                value: $setting['value'],
                type: 'user',
                module: null,
                userId: $id,
                valueType: $setting['value_type'] ?? null,
                options: $setting['options'] ?? null
            );
        }

        return ApiResponse::success($updated, 'Parametres utilisateur mis a jour.');
    }
}
