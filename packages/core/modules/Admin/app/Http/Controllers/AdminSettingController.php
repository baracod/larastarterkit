<?php

namespace Modules\Admin\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Requests\AdminSettingRequest;
use Modules\Admin\Models\AdminSetting;

class AdminSettingController
{
    public function index(): Collection
    {
        return AdminSetting::query()->get();
    }

    public function show(int $id): AdminSetting
    {
        return AdminSetting::query()->findOrFail($id);
    }

    public function store(AdminSettingRequest $request): AdminSetting
    {
        $validated = $request->validated();

        return AdminSetting::create($validated);
    }

    public function update(AdminSettingRequest $request, AdminSetting $adminSetting): AdminSetting
    {
        $validated = $request->validated();
        $adminSetting->update($validated);

        return $adminSetting;
    }

    public function destroy(int $id): JsonResponse
    {
        $record = AdminSetting::findOrFail($id);

        $record->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function destroyMultiple(Request $request): JsonResponse
    {
        $ids = $request->validate([
            '*' => ['required', 'integer', 'exists:admin_settings,id'],
        ]);

        $record = AdminSetting::whereIn('id', $ids);

        $record->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
