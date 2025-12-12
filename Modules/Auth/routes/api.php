<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Http\Middleware\EnsureUserIsActive;
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


Route::post('v1/auth/login', 'Modules\Auth\Http\Controllers\AuthController@login');
Route::post('v1/auth/forgotten-password', 'Modules\Auth\Http\Controllers\AuthController@forgottenPassword')->name('auth-forgotten-password');
Route::patch('v1/auth/reset-password', 'Modules\Auth\Http\Controllers\AuthController@resetPassword')->name('auth-reset-password');

Route::middleware(['auth:sanctum', EnsureUserIsActive::class])->prefix('v1/auth')->group(function () {
    //connection
    Route::get('logout', 'Modules\Auth\Http\Controllers\AuthController@logout');
    Route::get('user', 'Modules\Auth\Http\Controllers\AuthController@user');

    //user
    Route::patch('users/suspend-multiple', 'Modules\Auth\Http\Controllers\UserController@suspendMultiple')->name('auth-user-suspend-active')->whereNumber('id');
    Route::patch('users/active-multiple', 'Modules\Auth\Http\Controllers\UserController@reactivateMultiple')->name('auth-user-reactivate-multiple')->whereNumber('id');
    Route::post('users/{id}/suspend-active', 'Modules\Auth\Http\Controllers\UserController@suspendOrActive')->name('auth-user-suspend-active')->whereNumber('id');
    Route::put('users/change-password', 'Modules\Auth\Http\Controllers\UserController@changePassword')->name('auth-user-change-password');
    Route::delete('users/delete-multiple', ['Modules\Auth\Http\Controllers\UserController', 'destroyMultiple'])->name('auth-user-delete-multiple');
    Route::post('users/{id}/roles', ['Modules\Auth\Http\Controllers\UserController', 'setRolesToUser'])->name('auth-user-set-roles')->whereNumber('id');
    Route::post('users/update-profile/{id}', ['Modules\Auth\Http\Controllers\UserController', 'updateProfile'])->name('auth-user-update-profile')->whereNumber('id');
    Route::resource('users', 'Modules\Auth\Http\Controllers\UserController')->names('auth-user');

    //roles
    Route::delete('roles/delete-multiple', ['Modules\Auth\Http\Controllers\RoleController', 'destroyMultiple'])->name('auth-role-delete-multiple');
    Route::get('roles/{id}/permissions', 'Modules\Auth\Http\Controllers\RoleController@getPermissions')
        ->whereNumber('id')
        ->name('auth-role-get-permissions');
    Route::get('roles/{ids}/permissions', 'Modules\Auth\Http\Controllers\RoleController@getCommonPermissions')
        ->where('ids', '[0-9,]+')
        ->name('auth-role-get-common-permissions');
    Route::post('roles/{ids}/permissions', 'Modules\Auth\Http\Controllers\RoleController@attachPermissions')
        ->where('ids', '[0-9,]+')
        ->name('auth-role-attach-permissions');
    Route::delete('roles/{ids}/permissions', 'Modules\Auth\Http\Controllers\RoleController@detachPermissions')
        ->where('ids', '[0-9,]+')
        ->name('auth-role-detach-permissions');

    Route::resource('roles', 'Modules\Auth\Http\Controllers\RoleController')->names('auth-role');

    //permissions
    Route::delete('permissions/delete-multiple', ['Modules\Auth\Http\Controllers\PermissionController', 'destroyMultiple'])->name('auth-permission-delete-multiple');
    Route::resource('permissions', 'Modules\Auth\Http\Controllers\PermissionController')->names('auth-permission');
    //{{ next-route }}
});
