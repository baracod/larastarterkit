<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active', 'must_change_pass', 'administrator', \Baracod\Larastarterkit\Core\Http\Middleware\ProtectDocumentWorkflowSetting::class])->prefix('v1/admin')->group(function () {
    Route::get('connections', [\Modules\Admin\Http\Controllers\ConnectionDiagnosticsController::class, 'index']);
    Route::post('connections/check', [\Modules\Admin\Http\Controllers\ConnectionDiagnosticsController::class, 'check'])
        ->middleware('throttle:30,1');
    // Modules
    Route::get('modules', 'Modules\Admin\Http\Controllers\ModuleController@index')->name('admin-module-index');
    Route::post('modules/{name}/toggle', 'Modules\Admin\Http\Controllers\ModuleController@toggle')->name('admin-module-toggle');

    // Notifications
    Route::get('notifications/settings', 'Modules\Admin\Http\Controllers\NotificationController@getSettings')->name('admin-notification-settings');
    Route::put('notifications/settings', 'Modules\Admin\Http\Controllers\NotificationController@updateSetting')->name('admin-notification-settings-update');
    Route::post('notifications', 'Modules\Admin\Http\Controllers\NotificationController@store')->name('admin-notification-store');

    // Configs
    Route::get('configs/{module}', 'Modules\Admin\Http\Controllers\ModuleConfigController@index')->name('admin-config-index');
    Route::put('configs/{module}', 'Modules\Admin\Http\Controllers\ModuleConfigController@update')->name('admin-config-update');

    // Settings
    Route::get('settings', 'Modules\Admin\Http\Controllers\SettingController@index')->name('admin-setting-index');
    Route::get('settings/system', 'Modules\Admin\Http\Controllers\SettingController@system')->name('admin-setting-system');
    Route::get('settings/modules', 'Modules\Admin\Http\Controllers\SettingController@modules')->name('admin-setting-modules');
    Route::get('settings/users', 'Modules\Admin\Http\Controllers\SettingController@users')->name('admin-setting-users');
    Route::get('settings/modules-list', 'Modules\Admin\Http\Controllers\SettingController@modulesList')->name('admin-setting-modules-list');
    Route::get('settings/available-modules', 'Modules\Admin\Http\Controllers\SettingController@availableModules')->name('admin-setting-available-modules');
    Route::get('settings/{key}', 'Modules\Admin\Http\Controllers\SettingController@show')->name('admin-setting-show');
    Route::post('settings', 'Modules\Admin\Http\Controllers\SettingController@store')->name('admin-setting-store');
    Route::put('settings/{id}', 'Modules\Admin\Http\Controllers\SettingController@update')->name('admin-setting-update');
    Route::delete('settings', 'Modules\Admin\Http\Controllers\SettingController@destroy')->name('admin-setting-destroy');
    Route::post('settings/bulk', 'Modules\Admin\Http\Controllers\SettingController@bulkUpdate')->name('admin-setting-bulk-update');
    Route::post('settings/clear-cache', 'Modules\Admin\Http\Controllers\SettingController@clearCache')->name('admin-setting-clear-cache');

    Route::delete('admin-settings/delete-multiple', ['Modules\Admin\Http\Controllers\AdminSettingController', 'destroyMultiple'])->name('admin-admin-setting-delete-multiple');
    Route::apiResource('admin-settings', 'Modules\Admin\Http\Controllers\AdminSettingController')->names('admin-admin-setting');
});

Route::middleware(['auth:sanctum', 'active', 'must_change_pass'])->prefix('v1/admin')->group(function () {
    Route::get('notifications', 'Modules\Admin\Http\Controllers\NotificationController@index')->name('admin-notification-index');
    Route::get('notifications/unread-count', 'Modules\Admin\Http\Controllers\NotificationController@unreadCount')->name('admin-notification-unread-count');
    Route::post('notifications/{id}/read', 'Modules\Admin\Http\Controllers\NotificationController@markAsRead')->name('admin-notification-read');
    Route::post('notifications/read-all', 'Modules\Admin\Http\Controllers\NotificationController@markAllAsRead')->name('admin-notification-read-all');
});
