<?php

namespace Modules\Admin\Http\Controllers;

use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Admin\Http\Requests\CheckConnectionRequest;
use Modules\Admin\Services\ConnectionDiagnosticsService;

class ConnectionDiagnosticsController extends Controller
{
    public function index(ConnectionDiagnosticsService $diagnostics): JsonResponse
    {
        return response()->json($diagnostics->catalog())->header('Cache-Control', 'no-store');
    }

    public function check(CheckConnectionRequest $request, ConnectionDiagnosticsService $diagnostics): JsonResponse
    {
        return response()->json($diagnostics->check(
            $request->validated('service'), $request->validated('disk'),
        ))->header('Cache-Control', 'no-store');
    }
}
