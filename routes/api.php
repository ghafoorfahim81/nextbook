<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::apiResource('designations', App\Http\Controllers\Administration\DesignationController::class);


Route::apiResource('designations', App\Http\Controllers\Administration\DesignationController::class);


Route::apiResource('departments', App\Http\Controllers\Administration\DepartmentController::class);
