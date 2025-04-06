<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::apiResource('designations', App\Http\Controllers\Administration\DesignationController::class);


Route::apiResource('designations', App\Http\Controllers\Administration\DesignationController::class);


Route::apiResource('departments', App\Http\Controllers\Administration\DepartmentController::class);


Route::apiResource('categories', App\Http\Controllers\Administration\CategoryController::class);


Route::apiResource('branches', App\Http\Controllers\Administration\BranchController::class);
