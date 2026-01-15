<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes (if any)
// Route::get('/public/search', ...);

// Protected routes that require authentication
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/search/items-for-sale', [App\Http\Controllers\SearchController::class, 'searchItemsForSale'])
        ->name('api.search.items-for-sale');
    Route::post('/search/{resourceType}', [App\Http\Controllers\SearchController::class, 'search']);
    Route::get('/search/resource-types', [App\Http\Controllers\SearchController::class, 'getResourceTypes']);
});
