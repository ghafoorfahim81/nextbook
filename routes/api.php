<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



 
Route::post('/search/{resourceType}', [App\Http\Controllers\SearchController::class, 'search']);
Route::get('/search/resource-types', [App\Http\Controllers\SearchController::class, 'getResourceTypes']);
