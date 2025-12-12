<?php

namespace Modules\Auth\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Auth\Http\Requests\PermissionRequest;
use Modules\Auth\Models\Permission;

class PermissionController
{
    public function index()
    {
        return Permission::all();
    }

    public function show($id)
    {
        return Permission::findOrFail($id);
    }

    public function store(PermissionRequest $request)
    {
        $validated = $request->validated();

        return Permission::create($validated);
    }

    public function update(PermissionRequest $request, Permission $permission)
    {
        $validated = $request->validated();
        $permission->update($validated);

        return $permission;
    }

    public function destroy($id)
    {
        $model = Permission::findOrFail($id);

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->all();

        $model = Permission::whereIn('id', $ids);

        $model->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
