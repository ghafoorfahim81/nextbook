### Routing and Endpoints

#### API

```1:25:routes/api.php
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

 
Route::post('/search/{resourceType}', [App\Http\Controllers\SearchController::class, 'search']);
Route::get('/search/resource-types', [App\Http\Controllers\SearchController::class, 'getResourceTypes']);
```

- POST `/api/search/{resourceType}`: Full-text search across resources. See SearchController.
- GET `/api/search/resource-types`: Returns supported resource types.

Example (search items):
```bash
curl -X POST \
  -H 'Content-Type: application/json' \
  -d '{"search":"paracetamol","fields":["name","code"],"limit":10}' \
  http://localhost:8000/api/search/items
```

#### Web (Authenticated)

```1:84:routes/web.php
<?php

use App\Http\Controllers\Inventory\ItemFastEntryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Administration\DesignationController;
use App\Http\Controllers\Administration\DepartmentController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\CheckCompany;
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Public routes that don't require company check
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->group(function () {
        Route::get('/company/create', [\App\Http\Controllers\CompanyController::class, 'create'])
            ->name('company.create');

        Route::post('/company', [\App\Http\Controllers\CompanyController::class, 'store'])
            ->name('company.store');
    });

// Authenticated routes that require a company
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    CheckCompany::class,
])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('designations', DesignationController::class);
    Route::resource('/departments', DepartmentController::class);
    Route::resource('/categories', \App\Http\Controllers\Administration\CategoryController::class);
    Route::resource('/stores', \App\Http\Controllers\Administration\StoreController::class);
    Route::resource('/brands', \App\Http\Controllers\Administration\BrandController::class);
    Route::get('/departments/parents', [DepartmentController::class, 'getParents'])->name('departments.parents');
    Route::resource('/branches', \App\Http\Controllers\Administration\BranchController::class);
    Route::resource('account-types', \App\Http\Controllers\Account\AccountTypeController::class);
    Route::resource('chart-of-accounts', \App\Http\Controllers\Account\AccountController::class);
    Route::resource('/currencies', \App\Http\Controllers\Administration\CurrencyController::class);
    Route::resource('/unit-measures', \App\Http\Controllers\Administration\UnitMeasureController::class);
    Route::resource('/items', \App\Http\Controllers\Inventory\ItemController::class);

    Route::resource('/ledgers', \App\Http\Controllers\Ledger\LedgerController::class);
    Route::resource('/suppliers', \App\Http\Controllers\Ledger\SupplierController::class);
    Route::resource('/customers', \App\Http\Controllers\Ledger\CustomerController::class);
    Route::get('/item-fast-entry', [ItemFastEntryController::class,'create'])->name('item.fast.entry');
    Route::post('/item-fast-entry', [ItemFastEntryController::class, 'store'])
        ->name('item.fast.store');
    Route::get('/item-fast-opening', [\App\Http\Controllers\Inventory\FastOpeningController::class, 'index'])
        ->name('item.fast.opening');
    Route::post('/item-fast-opening', [\App\Http\Controllers\Inventory\FastOpeningController::class, 'store'])
        ->name('fast-opening.store');

    Route::resource('/purchases', \App\Http\Controllers\Purchase\PurchaseController::class);

    // Company routes
    Route::get('/company', [\App\Http\Controllers\CompanyController::class, 'show'])
        ->name('company.show');
    Route::patch('/company/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])
        ->name('company.update');

    // Search routes
    Route::post('/search/{resourceType}', [\App\Http\Controllers\SearchController::class, 'search']);
    Route::get('/search/resource-types', [\App\Http\Controllers\SearchController::class, 'getResourceTypes']);

});
```

Key resources and typical verbs:
- `/items` (index, create, store, show, edit, update, destroy)
- `/departments`, `/categories`, `/brands`, `/stores`, `/branches`, `/currencies`, `/unit-measures`
- `/ledgers`, `/suppliers`, `/customers`
- `/purchases`
- Fast entry/opening: `GET/POST /item-fast-entry`, `GET/POST /item-fast-opening`
