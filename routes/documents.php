<?php

use Baracod\Larastarterkit\Core\Documents\Http\Controllers\DocumentWorkflowController;
use Baracod\Larastarterkit\Core\Documents\Http\Controllers\ProcedureDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/documents')->middleware(['api', 'auth:sanctum', 'active', 'must_change_pass'])->group(function (): void {
    Route::get('policies', [ProcedureDocumentController::class, 'policies']);
    Route::middleware('administrator')->group(function (): void {
        Route::get('settings', [DocumentWorkflowController::class, 'index']);
        Route::put('settings', [DocumentWorkflowController::class, 'update']);
    });
    Route::get('{type}/{record}', [ProcedureDocumentController::class, 'index'])->whereNumber('record');
    Route::post('{type}/{record}', [ProcedureDocumentController::class, 'store'])->whereNumber('record');
    Route::post('{type}/{record}/preview', [ProcedureDocumentController::class, 'preview'])->whereNumber('record')->name('procedure-documents.preview');
    Route::post('{type}/{record}/generate', [ProcedureDocumentController::class, 'store'])->whereNumber('record')->name('procedure-documents.generate');
    Route::get('{type}/{record}/{document}', [ProcedureDocumentController::class, 'download'])->whereNumber(['record', 'document']);
});
