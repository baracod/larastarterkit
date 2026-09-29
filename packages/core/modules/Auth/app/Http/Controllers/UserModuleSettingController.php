<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Auth\Http\Requests\UpdateUserModuleSettingsRequest;
use Modules\Auth\Models\User;

class UserModuleSettingController extends Controller
{
    public function index(int $id): JsonResponse
    {
        abort_unless(auth()->id() === $id || auth()->user()->hasRole('administrator'), 403);
        $user = User::query()->findOrFail($id);

        return ApiResponse::success(['settings' => $user->moduleSettings()->get(['module', 'key', 'value'])]);
    }

    public function update(UpdateUserModuleSettingsRequest $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        DB::transaction(function () use ($user, $request): void {
            foreach ($request->validated('settings') as $setting) {
                $user->moduleSettings()->updateOrCreate(['module' => $setting['module'], 'key' => $setting['key']], ['value' => $setting['value']]);
            }
        });

        return ApiResponse::success(['settings' => $user->moduleSettings()->get(['module', 'key', 'value'])]);
    }
}
