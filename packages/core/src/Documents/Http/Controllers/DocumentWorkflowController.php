<?php

namespace Baracod\Larastarterkit\Core\Documents\Http\Controllers;

use Baracod\Larastarterkit\Core\Documents\Http\Requests\UpdateDocumentWorkflowRequest;
use Baracod\Larastarterkit\Core\Documents\Services\DocumentWorkflowService;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Services\SettingService;

class DocumentWorkflowController extends Controller
{
    public function index(DocumentWorkflowService $workflow): JsonResponse
    {
        return response()->json($workflow->configuration());
    }

    public function update(UpdateDocumentWorkflowRequest $request, DocumentWorkflowService $workflow): JsonResponse
    {
        DB::transaction(function () use ($request, $workflow): void {
            $before = $workflow->settings();
            $steps = $request->validated('steps');
            foreach ($steps as &$step) {
                $step['documents'] = array_filter($step['documents'] ?? [], fn ($mode) => $mode !== null);
            }
            unset($step);
            $after = array_replace($before, $steps);
            $setting = app(SettingService::class)->set(DocumentWorkflowService::SETTING_KEY, $after);
            activity('document_configuration')->causedBy($request->user())->performedOn($setting)
                ->withProperties(['before' => $before, 'after' => $after])->log('document_modes_updated');
        });

        return response()->json($workflow->configuration());
    }
}
