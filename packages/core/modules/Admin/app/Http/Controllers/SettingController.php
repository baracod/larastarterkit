<?php

namespace Modules\Admin\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Requests\StoreSettingRequest;
use Modules\Admin\Http\Requests\UpdateSettingRequest;
use Modules\Admin\Models\Setting;
use Modules\Admin\Services\SettingService;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settingService) {}

    /**
     * Get paginated settings with optional filters
     */
    public function index(Request $request): JsonResponse
    {
        $settings = $this->settingService->getPaginated(
            perPage: $request->input('per_page', 15),
            type: $request->input('type'),
            module: $request->input('module'),
            userId: $request->input('user_id'),
            search: $request->input('search')
        );

        return ApiResponse::success($settings);
    }

    /**
     * Get all system settings
     */
    public function system(): JsonResponse
    {
        $settings = $this->settingService->getSystemSettings();

        return ApiResponse::success($settings);
    }

    /**
     * Get all module settings or settings for a specific module
     */
    public function modules(Request $request): JsonResponse
    {
        $module = $request->input('module');
        $settings = $this->settingService->getModuleSettings($module);

        return ApiResponse::success($settings);
    }

    /**
     * Get all user settings or settings for a specific user
     */
    public function users(Request $request): JsonResponse
    {
        $userId = $request->input('user_id', auth()->id());
        $settings = $this->settingService->getUserSettings($userId);

        return ApiResponse::success($settings);
    }

    /**
     * Get list of all modules that have settings
     */
    public function modulesList(): JsonResponse
    {
        $modules = $this->settingService->getModulesList();

        return ApiResponse::success($modules);
    }

    /**
     * Get list of all available modules from configuration
     */
    public function availableModules(): JsonResponse
    {
        $modulesFile = base_path('modules_statuses.json');

        if (! file_exists($modulesFile)) {
            return ApiResponse::success([]);
        }

        $modulesData = json_decode(file_get_contents($modulesFile), true);
        $modules = array_keys($modulesData ?? []);
        sort($modules);

        return ApiResponse::success($modules);
    }

    /**
     * Get a specific setting by key
     */
    public function show(Request $request, string $key): JsonResponse
    {
        $value = $this->settingService->get(
            key: $key,
            userId: $request->input('user_id'),
            module: $request->input('module'),
            default: $request->input('default')
        );

        return ApiResponse::success(['key' => $key, 'value' => $value]);
    }

    /**
     * Create or update a setting
     */
    public function store(StoreSettingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $setting = $this->settingService->set(
            key: $data['key'],
            value: $data['value'],
            type: $data['type'] ?? 'system',
            module: $data['module'] ?? null,
            userId: $data['user_id'] ?? null,
            valueType: $data['value_type'] ?? null,
            options: $data['options'] ?? null
        );

        return ApiResponse::success($setting, 'Paramètre enregistré avec succès.');
    }

    /**
     * Update an existing setting
     */
    public function update(UpdateSettingRequest $request, int $id): JsonResponse
    {
        $setting = $this->settingService->find($id);

        if (! $setting) {
            return ApiResponse::error('Paramètre non trouvé.', 404);
        }

        $data = $request->validated();
        $currentValue = array_key_exists('value', $data)
            ? $data['value']
            : Setting::castValue($setting->value, $setting->value_type);

        $updated = $this->settingService->set(
            key: $data['key'] ?? $setting->key,
            value: $currentValue,
            type: $data['type'] ?? $setting->type,
            module: $data['module'] ?? $setting->module,
            userId: $data['user_id'] ?? $setting->user_id,
            valueType: $data['value_type'] ?? $setting->value_type,
            options: $data['options'] ?? $setting->options?->toArray()
        );

        return ApiResponse::success($updated, 'Paramètre mis à jour avec succès.');
    }

    /**
     * Delete a setting
     */
    public function destroy(Request $request): JsonResponse
    {
        $deleted = $this->settingService->delete(
            key: $request->input('key'),
            type: $request->input('type', 'system'),
            module: $request->input('module'),
            userId: $request->input('user_id')
        );

        if ($deleted) {
            return ApiResponse::success(null, 'Paramètre supprimé avec succès.');
        }

        return ApiResponse::error('Impossible de supprimer le paramètre.', 400);
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
        ]);

        $updated = $this->settingService->bulkUpdate($request->input('settings'));

        return ApiResponse::success($updated, 'Paramètres mis à jour avec succès.');
    }

    /**
     * Clear all settings cache
     */
    public function clearCache(): JsonResponse
    {
        $this->settingService->clearAllCache();

        return ApiResponse::success(null, 'Cache des paramètres vidé avec succès.');
    }
}
