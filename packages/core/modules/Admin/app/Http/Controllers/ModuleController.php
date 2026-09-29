<?php

namespace Modules\Admin\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    private function statusesPath(): string
    {
        return base_path('modules_statuses.json');
    }

    private function readStatuses(): array
    {
        $path = $this->statusesPath();

        return app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->statuses();
    }

    private function writeStatuses(array $statuses): void
    {
        file_put_contents($this->statusesPath(), json_encode($statuses, JSON_PRETTY_PRINT));
    }

    private function primaryModules(): array
    {
        return ['Auth', 'Admin'];
    }

    public function index(): JsonResponse
    {
        $statuses = $this->readStatuses();
        $primaryModules = $this->primaryModules();
        $modules = [];

        foreach ($statuses as $name => $enabled) {
            $isPrimary = in_array($name, $primaryModules);
            $moduleJsonPath = module_path($name, 'module.json');
            $description = '';

            if (file_exists($moduleJsonPath)) {
                $moduleJson = json_decode(file_get_contents($moduleJsonPath), true);
                $description = $moduleJson['description'] ?? '';
            }

            $modules[] = [
                'name' => $name,
                'enabled' => (bool) $enabled,
                'is_primary' => $isPrimary,
                'description' => $description,
            ];
        }

        return response()->json($modules);
    }

    public function toggle(Request $request, string $name): JsonResponse
    {
        $primaryModules = $this->primaryModules();
        if (in_array($name, $primaryModules)) {
            return response()->json(['message' => "Le module '{$name}' est primaire et ne peut pas être désactivé."], 403);
        }

        $statuses = $this->readStatuses();
        if (! isset($statuses[$name])) {
            return ApiResponse::notFound("Module '{$name}' non trouvé.");
        }

        $statuses[$name] = ! $statuses[$name];
        try {
            app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->statuses($statuses);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $this->writeStatuses($statuses);

        return ApiResponse::success(['name' => $name, 'enabled' => $statuses[$name]], 'Statut du module mis à jour.');
    }
}
