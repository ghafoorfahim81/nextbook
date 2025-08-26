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


Route::apiResource('stores', App\Http\Controllers\Administration\StoreController::class);


Route::apiResource('quantities', App\Http\Controllers\Administration\QuantityController::class);


Route::apiResource('quantities', App\Http\Controllers\Administration\QuantityController::class);


Route::apiResource('unit-measures', App\Http\Controllers\Administration\UnitMeasureController::class);


Route::apiResource('brands', App\Http\Controllers\Administration\BrandController::class);


Route::apiResource('transactions', App\Http\Controllers\Transaction\TransactionController::class);


Route::apiResource('ledger-openings', App\Http\Controllers\LedgerOpening\LedgerOpeningController::class);


Route::apiResource('ledgers', App\Http\Controllers\Ledger\LedgerController::class);


Route::apiResource('items', App\Http\Controllers\Inventory\ItemController::class);


Route::apiResource('companies', App\Http\Controllers\Administration\CompanyController::class);
