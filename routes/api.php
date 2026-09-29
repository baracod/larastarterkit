<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

Route::get('v1/modules', fn (\Baracod\Larastarterkit\Core\Support\ModuleRegistry $modules) => response()->json($modules->statuses())->header('Cache-Control', 'no-store'));
