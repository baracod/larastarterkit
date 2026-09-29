<?php

namespace Baracod\Larastarterkit\Core\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Http\Controllers\AdminSettingController;
use Modules\Admin\Http\Controllers\SettingController;
use Modules\Admin\Models\Setting;
use Symfony\Component\HttpFoundation\Response;

class ProtectDocumentWorkflowSetting
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! in_array($request->route()->getControllerClass(), [SettingController::class, AdminSettingController::class], true)) {
            return $next($request);
        }

        $keys = [$request->input('key')];
        foreach ((array) $request->input('settings', []) as $setting) {
            $keys[] = is_array($setting) ? ($setting['key'] ?? null) : null;
        }
        $ids = [];
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Model) {
                $ids[] = $parameter->getKey();
            } elseif (is_scalar($parameter) && ctype_digit((string) $parameter)) {
                $ids[] = $parameter;
            }
        }
        if ($request->route()->getActionMethod() === 'destroyMultiple') {
            foreach ($request->all() as $id) {
                if (is_scalar($id) && ctype_digit((string) $id)) {
                    $ids[] = $id;
                }
            }
        }
        if (in_array('document_workflow', $keys, true)
            || ($ids !== [] && Setting::whereKey($ids)->where('key', 'document_workflow')->exists())) {
            throw ValidationException::withMessages(['key' => __('document_generation.reserved_setting')]);
        }

        return DB::transaction(function () use ($request, $next): Response {
            $protected = fn () => Setting::where('key', 'document_workflow')->orderBy('id')->get()->map->getAttributes()->all();
            $before = $protected();
            $response = $next($request);
            if ($before !== $protected()) {
                throw ValidationException::withMessages(['key' => __('document_generation.reserved_setting')]);
            }

            return $response;
        });
    }
}
