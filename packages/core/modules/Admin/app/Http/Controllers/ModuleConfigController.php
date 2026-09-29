<?php

namespace Modules\Admin\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Models\ModuleConfig;

class ModuleConfigController extends Controller
{
    public function index(string $module): JsonResponse
    {
        $configs = ModuleConfig::where('module_name', $module)->get();

        return response()->json($configs);
    }

    public function update(Request $request, string $module): JsonResponse
    {
        $data = $request->validate([
            'configs' => 'required|array',
            'configs.*.key' => 'required|string',
            'configs.*.value' => 'required',
            'configs.*.type' => 'nullable|string',
            'configs.*.description' => 'nullable|string',
        ]);

        foreach ($data['configs'] as $config) {
            ModuleConfig::setValue(
                $module,
                $config['key'],
                $config['value'],
                $config['type'] ?? 'string',
                $config['description'] ?? null
            );
        }

        return ApiResponse::success(null, "Configurations du module '{$module}' mises à jour.");
    }
}
