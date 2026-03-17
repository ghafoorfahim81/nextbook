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


Route::apiResource('purchase-payments', App\Http\Controllers\Purchase\PurchasePaymentController::class);
