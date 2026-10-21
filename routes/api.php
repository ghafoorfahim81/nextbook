<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public routes (if any)
// Route::get('/public/search', ...);

// Protected routes that require authentication.
//
// These are the only authenticated routes in the application that do not pass
// through the `web` middleware group, so they do not inherit SetActiveCompany
// and SetDatabaseCompanyContext from it. Both are listed explicitly because the
// search endpoints query items, ledgers and accounts — all three are under row
// level security, and without the company on the connection they would return
// an empty result set rather than an error.
Route::middleware([
    'auth:sanctum',
    App\Http\Middleware\SetActiveCompany::class,
    App\Http\Middleware\SetDatabaseCompanyContext::class,
])->group(function () {
    Route::post('/search/items-list', [App\Http\Controllers\SearchController::class, 'searchItemsList'])
        ->name('api.search.items-list');
    Route::post('/search/{resourceType}', [App\Http\Controllers\SearchController::class, 'search']);
    Route::get('/search/resource-types', [App\Http\Controllers\SearchController::class, 'getResourceTypes']);
});

Route::middleware([
    'web',
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/notifications/feed', [App\Http\Controllers\NotificationController::class, 'feed'])
        ->name('api.notifications.feed');
    Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])
        ->name('api.notifications.read-all');
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])
        ->name('api.notifications.read');
});


Route::apiResource('quantities', App\Http\Controllers\Administration\QuantityController::class);
Route::delete('/quantities/{quantity}/force-delete', [App\Http\Controllers\Administration\QuantityController::class, 'forceDelete'])
    ->name('quantities.force-delete')
    ->withTrashed();

Route::middleware([
    'web',
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/landed-costs', [App\Http\Controllers\Inventory\LandedCostController::class, 'index']);
    Route::get('/landed-costs/{landedCost}', [App\Http\Controllers\Inventory\LandedCostController::class, 'show']);
    Route::post('/landed-costs', [App\Http\Controllers\Inventory\LandedCostController::class, 'store']);
    Route::put('/landed-costs/{landedCost}', [App\Http\Controllers\Inventory\LandedCostController::class, 'update']);
    Route::delete('/landed-costs/{landedCost}', [App\Http\Controllers\Inventory\LandedCostController::class, 'destroy']);
    Route::post('/landed-costs/{landedCost}/allocate', [App\Http\Controllers\Inventory\LandedCostController::class, 'allocate']);
    Route::post('/landed-costs/{landedCost}/post', [App\Http\Controllers\Inventory\LandedCostController::class, 'post']);
});
