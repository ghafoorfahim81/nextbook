<?php

use App\Http\Controllers\NextController;


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
    Route::patch('/departments/{department}/restore', [\App\Http\Controllers\Administration\DepartmentController::class, 'restore'])->name('departments.restore')->withTrashed();
    Route::resource('/categories', \App\Http\Controllers\Administration\CategoryController::class);
    Route::patch('/categories/{category}/restore', [\App\Http\Controllers\Administration\CategoryController::class, 'restore'])->name('categories.restore')->withTrashed();
    Route::resource('/stores', \App\Http\Controllers\Administration\StoreController::class);
    Route::patch('/stores/{store}/restore', [\App\Http\Controllers\Administration\StoreController::class, 'restore'])->name('stores.restore')->withTrashed();
    Route::resource('/companies', \App\Http\Controllers\Administration\CompanyController::class);
    Route::patch('/companies/{company}/restore', [\App\Http\Controllers\Administration\CompanyController::class, 'restore'])->name('companies.restore')->withTrashed();
    Route::resource('/brands', \App\Http\Controllers\Administration\BrandController::class);
    Route::patch('/brands/{brand}/restore', [\App\Http\Controllers\Administration\BrandController::class, 'restore'])->name('brands.restore')->withTrashed();
    Route::get('/departments/parents', [DepartmentController::class, 'getParents'])->name('departments.parents');
    Route::resource('/branches', \App\Http\Controllers\Administration\BranchController::class);
    Route::patch('/branches/{branch}/restore', [\App\Http\Controllers\Administration\BranchController::class, 'restore'])->name('branches.restore')->withTrashed();
    Route::resource('account-types', \App\Http\Controllers\Account\AccountTypeController::class);
    Route::patch('/account-types/{accountType}/restore', [\App\Http\Controllers\Account\AccountTypeController::class, 'restore'])->name('account-types.restore')->withTrashed();
    Route::resource('chart-of-accounts', \App\Http\Controllers\Account\AccountController::class);
    Route::resource('/currencies', \App\Http\Controllers\Administration\CurrencyController::class);
    Route::patch('/currencies/{currency}/restore', [\App\Http\Controllers\Administration\CurrencyController::class, 'restore'])->name('currencies.restore')->withTrashed();
    Route::resource('/unit-measures', \App\Http\Controllers\Administration\UnitMeasureController::class);
    Route::patch('/unit-measures/{unitMeasure}/restore', [\App\Http\Controllers\Administration\UnitMeasureController::class, 'restore'])->name('unit-measures.restore')->withTrashed();

    Route::resource('/items', \App\Http\Controllers\Inventory\ItemController::class);

    Route::resource('/ledgers', \App\Http\Controllers\Ledger\LedgerController::class);
    Route::patch('/ledgers/{ledger}/restore', [\App\Http\Controllers\Ledger\LedgerController::class, 'restore'])->name('ledgers.restore')->withTrashed();
    Route::resource('/suppliers', \App\Http\Controllers\Ledger\SupplierController::class);
    Route::patch('/suppliers/{supplier}/restore', [\App\Http\Controllers\Ledger\SupplierController::class, 'restore'])->name('suppliers.restore')->withTrashed();
    Route::resource('/customers', \App\Http\Controllers\Ledger\CustomerController::class);
    Route::patch('/customers/{customer}/restore', [\App\Http\Controllers\Ledger\CustomerController::class, 'restore'])->name('customers.restore')->withTrashed();
    Route::get('/item-fast-entry', [ItemFastEntryController::class, 'create'])->name('item.fast.entry');
    Route::post('/item-fast-entry', [ItemFastEntryController::class, 'store'])
        ->name('item.fast.store');
    Route::get('/item-fast-opening', [\App\Http\Controllers\Inventory\FastOpeningController::class, 'index'])
        ->name('item.fast.opening');
    Route::post('/item-fast-opening', [\App\Http\Controllers\Inventory\FastOpeningController::class, 'store'])
        ->name('fast-opening.store');

    Route::resource('/purchases', \App\Http\Controllers\Purchase\PurchaseController::class);
    Route::patch('/purchases/{purchase}/restore', [\App\Http\Controllers\Purchase\PurchaseController::class, 'restore'])->name('purchases.restore')->withTrashed();
    //    Route::post('item_entry/Store', ['as' => 'item_entry.store', 'uses' => 'FastEntry\ItemEntryController@store'])->middleware(['dbconfig','auth:sanctum']);
    Route::get('/purchase-item-change', [NextController::class, 'purchaseItemChange'])->name('purchase.item.change');

    // Company routes
    Route::get('/company', [\App\Http\Controllers\CompanyController::class, 'show'])
        ->name('company.show');
    Route::patch('/company/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])
        ->name('company.update');
    Route::patch('/company/{company}/restore', [\App\Http\Controllers\CompanyController::class, 'restore'])->name('company.restore')->withTrashed();

    // Search routes
    Route::post('/search/{resourceType}', [\App\Http\Controllers\SearchController::class, 'search']);
    Route::get('/search/resource-types', [\App\Http\Controllers\SearchController::class, 'getResourceTypes']);
});
