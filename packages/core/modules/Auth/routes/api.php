<?php

use Illuminate\Support\Facades\Route;

// use Modules\Auth\Http\Controllers\AuthController;

/*
 *--------------------------------------------------------------------------
 * API Routes
 *--------------------------------------------------------------------------
 *
 * Here is where you can register API routes for your application. These
 * routes are loaded by the RouteServiceProvider within a group which
 * is assigned the "api" middleware group. Enjoy building your API!
 *
*/

// Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
//     Route::apiResource('auth', AuthController::class)->names('auth');
// });

Route::post('v1/auth/login', 'Modules\Auth\Http\Controllers\AuthController@login')
    ->middleware('throttle:'.config('auth.throttle.login').',1')
    ->name('auth-login');
Route::post('v1/auth/forgotten-password', 'Modules\Auth\Http\Controllers\AuthController@forgottenPassword')
    ->middleware('throttle:'.config('auth.throttle.password_reset').',10')
    ->name('auth-forgotten-password');
Route::patch('v1/auth/reset-password', 'Modules\Auth\Http\Controllers\AuthController@resetPassword')
    ->middleware('throttle:'.config('auth.throttle.password_reset').',10')
    ->name('auth-reset-password');

// Routes protégées sans restriction must_change_pass (pour permettre le changement de MdP)
Route::middleware(['auth:sanctum', 'active'])->prefix('v1/auth')->group(function () {
    Route::post('logout', 'Modules\Auth\Http\Controllers\AuthController@logout');
    Route::get('user', 'Modules\Auth\Http\Controllers\AuthController@user');
    Route::put('users/{id}/security/password', 'Modules\Auth\Http\Controllers\UserSecurityController@changePassword')->name('auth-user-security-change-password')->whereNumber('id');
    Route::post('users/force-change-password', 'Modules\Auth\Http\Controllers\UserSecurityController@forceChangePassword')->name('auth-user-force-change-password');
});

Route::middleware(['auth:sanctum', 'active', 'must_change_pass', \Baracod\Larastarterkit\Core\Http\Middleware\ProtectPrivilegedAccounts::class])->prefix('v1/auth')->group(function () {
    Route::post('users/{id}/login-instructions', 'Modules\Auth\Http\Controllers\UserController@sendLoginInstructions')->whereNumber('id')->middleware('throttle:5,1')->name('auth-user-login-instructions');
    // user
    Route::patch('users/suspend-multiple', 'Modules\Auth\Http\Controllers\UserController@suspendMultiple')->middleware('ability:edit,auth_users')->name('auth-user-suspend-active')->whereNumber('id');
    Route::patch('users/active-multiple', 'Modules\Auth\Http\Controllers\UserController@reactivateMultiple')->middleware('ability:edit,auth_users')->name('auth-user-reactivate-multiple')->whereNumber('id');
    Route::post('users/{id}/suspend-active', 'Modules\Auth\Http\Controllers\UserController@suspendOrActive')->middleware('ability:edit,auth_users')->name('auth-user-suspend-active')->whereNumber('id');
    Route::put('users/change-password', 'Modules\Auth\Http\Controllers\UserController@changePassword')->name('auth-user-change-password');
    Route::delete('users/delete-multiple', ['Modules\Auth\Http\Controllers\UserController', 'destroyMultiple'])->middleware('ability:delete,auth_users')->name('auth-user-delete-multiple');
    Route::post('users/{id}/roles', ['Modules\Auth\Http\Controllers\UserController', 'setRolesToUser'])->middleware('ability:edit,auth_users')->middleware('administrator')->name('auth-user-set-roles')->whereNumber('id');
    Route::post('users/update-profile/{id}', ['Modules\Auth\Http\Controllers\UserController', 'updateProfile'])->name('auth-user-update-profile')->whereNumber('id');
    Route::get('users/{id}/modules', 'Modules\Auth\Http\Controllers\UserModuleSettingController@index')->name('auth-user-modules-index')->whereNumber('id');
    Route::put('users/{id}/modules', 'Modules\Auth\Http\Controllers\UserModuleSettingController@update')->name('auth-user-modules-update')->whereNumber('id');
    Route::get('users/{id}/settings', 'Modules\Auth\Http\Controllers\UserSettingController@index')->name('auth-user-settings-index')->whereNumber('id');
    Route::put('users/{id}/settings', 'Modules\Auth\Http\Controllers\UserSettingController@update')->name('auth-user-settings-update')->whereNumber('id');
    Route::get('users/{id}/notifications', 'Modules\Auth\Http\Controllers\UserNotificationController@index')->name('auth-user-notification-index')->whereNumber('id');
    Route::post('users/{id}/notifications/{notificationId}/read', 'Modules\Auth\Http\Controllers\UserNotificationController@markAsRead')->name('auth-user-notification-read')->whereNumber('id')->whereNumber('notificationId');
    Route::post('users/{id}/notifications/read-all', 'Modules\Auth\Http\Controllers\UserNotificationController@markAllAsRead')->name('auth-user-notification-read-all')->whereNumber('id');
    Route::resource('users', 'Modules\Auth\Http\Controllers\UserController')->names('auth-user')
        ->middlewareFor('index', 'ability:browse,auth_users')
        ->middlewareFor(['create', 'store'], 'ability:add,auth_users')
        ->middlewareFor(['edit', 'update'], 'ability:edit,auth_users')
        ->middlewareFor('destroy', 'ability:delete,auth_users');

    // roles
    Route::delete('roles/delete-multiple', ['Modules\Auth\Http\Controllers\RoleController', 'destroyMultiple'])->middleware('ability:delete,auth_roles')->middleware('administrator')->name('auth-role-delete-multiple');
    Route::get('roles/{id}/permissions', 'Modules\Auth\Http\Controllers\RoleController@getPermissions')
        ->whereNumber('id')
        ->middleware('ability:browse,auth_roles')
        ->name('auth-role-get-permissions');
    Route::get('roles/{ids}/permissions', 'Modules\Auth\Http\Controllers\RoleController@getCommonPermissions')
        ->where('ids', '[0-9,]+')
        ->middleware('ability:browse,auth_roles')
        ->name('auth-role-get-common-permissions');
    Route::post('roles/{ids}/permissions', 'Modules\Auth\Http\Controllers\RoleController@attachPermissions')
        ->where('ids', '[0-9,]+')
        ->middleware('ability:edit,auth_roles')
        ->middleware('administrator')->name('auth-role-attach-permissions');
    Route::delete('roles/{ids}/permissions', 'Modules\Auth\Http\Controllers\RoleController@detachPermissions')
        ->where('ids', '[0-9,]+')
        ->middleware('ability:edit,auth_roles')
        ->middleware('administrator')->name('auth-role-detach-permissions');

    Route::resource('roles', 'Modules\Auth\Http\Controllers\RoleController')->names('auth-role')
        ->middlewareFor(['index', 'show'], 'ability:browse,auth_roles')
        ->middlewareFor(['create', 'store'], ['administrator', 'ability:add,auth_roles'])
        ->middlewareFor(['edit', 'update'], ['administrator', 'ability:edit,auth_roles'])
        ->middlewareFor('destroy', ['administrator', 'ability:delete,auth_roles']);

    // permissions
    Route::delete('permissions/delete-multiple', ['Modules\Auth\Http\Controllers\PermissionController', 'destroyMultiple'])->middleware('ability:delete,auth_permissions')->middleware('administrator')->name('auth-permission-delete-multiple');
    Route::resource('permissions', 'Modules\Auth\Http\Controllers\PermissionController')->names('auth-permission')
        ->middlewareFor(['index', 'show'], 'ability:browse,auth_permissions')
        ->middlewareFor(['create', 'store'], ['administrator', 'ability:add,auth_permissions'])
        ->middlewareFor(['edit', 'update'], ['administrator', 'ability:edit,auth_permissions'])
        ->middlewareFor('destroy', ['administrator', 'ability:delete,auth_permissions']);

    // Notification preferences
    Route::get('notification-preferences', 'Modules\Auth\Http\Controllers\NotificationPreferenceController@index')->name('auth-notification-preference-index');
    Route::get('notification-preferences/{category}', 'Modules\Auth\Http\Controllers\NotificationPreferenceController@show')->name('auth-notification-preference-show');
    Route::put('notification-preferences/{category}', 'Modules\Auth\Http\Controllers\NotificationPreferenceController@update')->name('auth-notification-preference-update');
    Route::post('notification-preferences/{category}/reset', 'Modules\Auth\Http\Controllers\NotificationPreferenceController@reset')->name('auth-notification-preference-reset');
    Route::post('notification-preferences/{category}/disable', 'Modules\Auth\Http\Controllers\NotificationPreferenceController@disable')->name('auth-notification-preference-disable');
    Route::post('notification-preferences/{category}/enable', 'Modules\Auth\Http\Controllers\NotificationPreferenceController@enable')->name('auth-notification-preference-enable');
    // {{ next-route }}

});
