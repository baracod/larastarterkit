<?php

use Baracod\Larastarterkit\Core\Http\Middleware\EnsureModuleEnabled;
use Illuminate\Support\Facades\Route;
use Modules\Documentation\Http\Controllers\CmsController;

Route::prefix('api/v1/documentation')->middleware(['api', 'auth:sanctum', 'active', 'must_change_pass', EnsureModuleEnabled::class.':Documentation', 'ability:browse,documentation'])->group(function () {
    Route::get('site-settings', [\Modules\Documentation\Http\Controllers\SiteSettingsController::class, 'show']);
    Route::put('site-settings', [\Modules\Documentation\Http\Controllers\SiteSettingsController::class, 'update'])->middleware('ability:edit,documentation');
    Route::get('collections', [CmsController::class, 'collections']);
    Route::get('editions/{edition}/pages', [CmsController::class, 'pages']);
    Route::get('pages/{page}/revisions', [CmsController::class, 'revisions']);
    Route::get('collections/{collection}/images', [CmsController::class, 'images']);
    Route::get('images/{image}/file', [CmsController::class, 'imageFile']);
    Route::get('publications', [CmsController::class, 'publications']);
    Route::post('collections/{collection}/preview', [CmsController::class, 'preview']);
    Route::middleware('ability:edit,documentation')->group(function () {
        Route::post('collections', [CmsController::class, 'collection']);
        Route::put('collections/{collection}', [CmsController::class, 'collection']);
        Route::post('collections/{collection}/editions', [CmsController::class, 'edition']);
        Route::put('collections/{collection}/editions/{edition}', [CmsController::class, 'edition']);
        Route::post('editions/{edition}/pages', [CmsController::class, 'page']);
        Route::put('editions/{edition}/pages/{page}', [CmsController::class, 'page']);
        Route::post('editions/{edition}/pages/reorder', [CmsController::class, 'reorder']);
        Route::patch('{kind}/{id}/archive', [CmsController::class, 'archive'])->where('kind', 'collections|editions|pages')->whereNumber('id');
    });
    Route::post('collections/{collection}/images', [CmsController::class, 'upload'])->middleware('ability:media,documentation');
    Route::patch('images/{image}/archive', [CmsController::class, 'archiveImage'])->middleware('ability:media,documentation');
    Route::patch('images/{image}', [CmsController::class, 'updateImage'])->middleware('ability:media,documentation');
    Route::post('editions/{edition}/publish', [CmsController::class, 'publish'])->middleware(['ability:publish,documentation', 'throttle:10,1']);
    Route::post('pages/{page}/revisions/{revision}/restore', [CmsController::class, 'restorePage'])->middleware(['ability:restore,documentation', 'ability:edit,documentation'])->whereNumber('revision');
    Route::post('publications/{publication}/restore', [CmsController::class, 'restorePublication'])->middleware(['ability:restore,documentation', 'ability:publish,documentation', 'throttle:10,1']);
});
