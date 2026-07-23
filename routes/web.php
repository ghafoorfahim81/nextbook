<?php

use App\Http\Controllers\NextController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\Administration\BranchController;
use App\Http\Controllers\Inventory\BarcodePrintController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DeletedRecordController;
use App\Http\Controllers\Inventory\ItemFastEntryController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Administration\DesignationController;
use App\Http\Controllers\Administration\DepartmentController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\CheckCompany;
use App\Http\Controllers\Administration\SwitchBranchController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QuickCreateController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::post('/locale', LocaleController::class)->name('locale.update');

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
    // Home page (default after login)
    Route::get('/profile', [ProfileController::class, 'show'])->name('user.profile');

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/home/exchange', [HomeController::class, 'exchange'])->name('home.exchange');
    Route::post('/home/unit-convert', [HomeController::class, 'unitConvert'])->name('home.unit-convert');
    Route::get('/home/weather', [HomeController::class, 'weather'])->name('home.weather');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/whats-new', fn () => Inertia::render('WhatsNew'))->name('whats-new');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/deleted-records', [DeletedRecordController::class, 'index'])->name('deleted-records.index');
    Route::patch('/deleted-records/{module}/{record}', [DeletedRecordController::class, 'restore'])->name('deleted-records.restore');
    Route::delete('/deleted-records/{module}/{record}', [DeletedRecordController::class, 'destroy'])->name('deleted-records.destroy');


    Route::resource('designations', DesignationController::class);
    Route::delete('/designations/{designation}/force-delete', [DesignationController::class, 'forceDelete'])
        ->name('designations.force-delete')
        ->withTrashed();
    Route::resource('/departments', DepartmentController::class);
    Route::patch('/departments/{department}/restore', [\App\Http\Controllers\Administration\DepartmentController::class, 'restore'])->name('departments.restore')->withTrashed();
    Route::delete('/departments/{department}/force-delete', [\App\Http\Controllers\Administration\DepartmentController::class, 'forceDelete'])
        ->name('departments.force-delete')
        ->withTrashed();
    Route::resource('/categories', \App\Http\Controllers\Administration\CategoryController::class);
    Route::patch('/categories/{category}/restore', [\App\Http\Controllers\Administration\CategoryController::class, 'restore'])->name('categories.restore')->withTrashed();
    Route::delete('/categories/{category}/force-delete', [\App\Http\Controllers\Administration\CategoryController::class, 'forceDelete'])
        ->name('categories.force-delete')
        ->withTrashed();
    Route::resource('/customer-groups', \App\Http\Controllers\Administration\CustomerGroupController::class)
        ->except(['create', 'edit']);
    Route::resource('/payment-terms', \App\Http\Controllers\Administration\PaymentTermController::class)
        ->except(['create', 'edit']);
    Route::resource('/warehouses', \App\Http\Controllers\Administration\WarehouseController::class);
    Route::patch('/warehouses/{warehouse}/restore', [\App\Http\Controllers\Administration\WarehouseController::class, 'restore'])->name('warehouses.restore')->withTrashed();
    Route::delete('/warehouses/{warehouse}/force-delete', [\App\Http\Controllers\Administration\WarehouseController::class, 'forceDelete'])
        ->name('warehouses.force-delete')
        ->withTrashed();
    Route::resource('/companies', \App\Http\Controllers\Administration\CompanyController::class);
    Route::patch('/companies/{company}/restore', [\App\Http\Controllers\Administration\CompanyController::class, 'restore'])->name('companies.restore')->withTrashed();
    Route::resource('/brands', \App\Http\Controllers\Administration\BrandController::class);
    Route::patch('/brands/{brand}/restore', [\App\Http\Controllers\Administration\BrandController::class, 'restore'])->name('brands.restore')->withTrashed();
    Route::delete('/brands/{brand}/force-delete', [\App\Http\Controllers\Administration\BrandController::class, 'forceDelete'])
        ->name('brands.force-delete')
        ->withTrashed();
    Route::get('/departments/parents', [DepartmentController::class, 'getParents'])->name('departments.parents');
    Route::resource('/branches', \App\Http\Controllers\Administration\BranchController::class);
    Route::patch('/branches/{branch}/restore', [\App\Http\Controllers\Administration\BranchController::class, 'restore'])->name('branches.restore')->withTrashed();
    Route::delete('/branches/{branch}/force-delete', [\App\Http\Controllers\Administration\BranchController::class, 'forceDelete'])
        ->name('branches.force-delete')
        ->withTrashed();
    Route::resource('account-types', \App\Http\Controllers\Account\AccountTypeController::class);
    Route::patch('/account-types/{accountType}/restore', [\App\Http\Controllers\Account\AccountTypeController::class, 'restore'])->name('account-types.restore')->withTrashed();
    Route::delete('/account-types/{accountType}/force-delete', [\App\Http\Controllers\Account\AccountTypeController::class, 'forceDelete'])
        ->name('account-types.force-delete')
        ->withTrashed();
    Route::get('/chart-of-accounts/export', [\App\Http\Controllers\Account\AccountController::class, 'exportList'])->name('chart-of-accounts.export');
    Route::resource('chart-of-accounts', \App\Http\Controllers\Account\AccountController::class);
    Route::patch('/chart-of-accounts/{chart_of_account}/restore', [\App\Http\Controllers\Account\AccountController::class, 'restore'])->name('chart-of-accounts.restore')->withTrashed();
    Route::get('/chart-of-accounts/{chart_of_account}/export-transactions', [\App\Http\Controllers\Account\AccountController::class, 'exportTransactions'])->name('chart-of-accounts.export-transactions');
    Route::delete('/chart-of-accounts/{chart_of_account}/force-delete', [\App\Http\Controllers\Account\AccountController::class, 'forceDelete'])
        ->name('chart-of-accounts.force-delete')
        ->withTrashed();
    Route::resource('/currencies', \App\Http\Controllers\Administration\CurrencyController::class);
    Route::patch('/currencies/{currency}/restore', [\App\Http\Controllers\Administration\CurrencyController::class, 'restore'])->name('currencies.restore')->withTrashed();
    Route::delete('/currencies/{currency}/force-delete', [\App\Http\Controllers\Administration\CurrencyController::class, 'forceDelete'])
        ->name('currencies.force-delete')
        ->withTrashed();
    Route::get('/currency-rate-updates', [\App\Http\Controllers\Administration\CurrencyRateUpdateController::class, 'index'])->name('currency-rate-updates.index');
    Route::post('/currency-rate-updates', [\App\Http\Controllers\Administration\CurrencyRateUpdateController::class, 'store'])->name('currency-rate-updates.store');
    Route::resource('/unit-measures', \App\Http\Controllers\Administration\UnitMeasureController::class);
    Route::patch('/unit-measures/{unitMeasure}/restore', [\App\Http\Controllers\Administration\UnitMeasureController::class, 'restore'])->name('unit-measures.restore')->withTrashed();
    Route::delete('/unit-measures/{unitMeasure}/force-delete', [\App\Http\Controllers\Administration\UnitMeasureController::class, 'forceDelete'])
        ->name('unit-measures.force-delete')
        ->withTrashed();
    Route::resource('/sizes', \App\Http\Controllers\Administration\SizeController::class);
    Route::patch('/sizes/{size}/restore', [\App\Http\Controllers\Administration\SizeController::class, 'restore'])->name('sizes.restore')->withTrashed();
    Route::delete('/sizes/{size}/force-delete', [\App\Http\Controllers\Administration\SizeController::class, 'forceDelete'])
        ->name('sizes.force-delete')
        ->withTrashed();

    Route::get('/items/export', [\App\Http\Controllers\Inventory\ItemController::class, 'export'])->name('items.export');
    Route::resource('/items', \App\Http\Controllers\Inventory\ItemController::class);
    Route::patch('/items/{item}/restore', [\App\Http\Controllers\Inventory\ItemController::class, 'restore'])->name('items.restore')->withTrashed();
    Route::get('/item-pricing', [\App\Http\Controllers\Inventory\ItemPricingController::class, 'index'])->name('item-pricing.index');
    Route::patch('/item-pricing/{item}', [\App\Http\Controllers\Inventory\ItemPricingController::class, 'update'])->name('item-pricing.update');
    Route::delete('/items/{item}/force-delete', [\App\Http\Controllers\Inventory\ItemController::class, 'forceDelete'])
        ->name('items.force-delete')
        ->withTrashed();
    Route::resource('/landed-costs', \App\Http\Controllers\Inventory\LandedCostController::class);
    Route::get('/stock-adjustments/export', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'export'])->name('stock-adjustments.export');
    Route::resource('/stock-adjustments', \App\Http\Controllers\Inventory\StockAdjustmentController::class)
        ->parameters(['stock-adjustments' => 'stockAdjustment']);
    Route::post('/stock-adjustments/{stockAdjustment}/post', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'post'])->name('stock-adjustments.post');
    Route::post('/stock-adjustments/{stockAdjustment}/reverse', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'reverse'])->name('stock-adjustments.reverse');
    Route::patch('/stock-adjustments/{stockAdjustment}/restore', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'restore'])->name('stock-adjustments.restore')->withTrashed();
    Route::delete('/stock-adjustments/{stockAdjustment}/force-delete', [\App\Http\Controllers\Inventory\StockAdjustmentController::class, 'forceDelete'])
        ->name('stock-adjustments.force-delete')
        ->withTrashed();
    Route::resource('/ledgers', \App\Http\Controllers\Ledger\LedgerController::class);
    Route::patch('/ledgers/{ledger}/restore', [\App\Http\Controllers\Ledger\LedgerController::class, 'restore'])->name('ledgers.restore')->withTrashed();
    Route::delete('/ledgers/{ledger}/force-delete', [\App\Http\Controllers\Ledger\LedgerController::class, 'forceDelete'])
        ->name('ledgers.force-delete')
        ->withTrashed();
    Route::get('/suppliers/list-export', [\App\Http\Controllers\Ledger\SupplierController::class, 'exportList'])->name('suppliers.list-export');
    Route::resource('/suppliers', \App\Http\Controllers\Ledger\SupplierController::class);
    Route::patch('/suppliers/{supplier}/restore', [\App\Http\Controllers\Ledger\SupplierController::class, 'restore'])->name('suppliers.restore')->withTrashed();
    Route::get('/suppliers/{supplier}/export', [\App\Http\Controllers\Ledger\SupplierController::class, 'export'])->name('suppliers.export');
    Route::get('/customers/list-export', [\App\Http\Controllers\Ledger\CustomerController::class, 'exportList'])->name('customers.list-export');
    Route::resource('/customers', \App\Http\Controllers\Ledger\CustomerController::class);
    Route::patch('/customers/{customer}/restore', [\App\Http\Controllers\Ledger\CustomerController::class, 'restore'])->name('customers.restore')->withTrashed();
    Route::get('/customers/{customer}/export', [\App\Http\Controllers\Ledger\CustomerController::class, 'export'])->name('customers.export');
    Route::delete('/suppliers/{supplier}/force-delete', [\App\Http\Controllers\Ledger\SupplierController::class, 'forceDelete'])
        ->name('suppliers.force-delete')
        ->withTrashed();
    Route::resource('/customers', \App\Http\Controllers\Ledger\CustomerController::class);
    Route::patch('/customers/{customer}/restore', [\App\Http\Controllers\Ledger\CustomerController::class, 'restore'])->name('customers.restore')->withTrashed();
    Route::delete('/customers/{customer}/force-delete', [\App\Http\Controllers\Ledger\CustomerController::class, 'forceDelete'])
        ->name('customers.force-delete')
        ->withTrashed();
    Route::get('/item-fast-entry', [ItemFastEntryController::class, 'create'])->name('item.fast.entry');
    Route::post('/item-fast-entry', [ItemFastEntryController::class, 'store'])
        ->name('item.fast.store');
    Route::get('/item-fast-opening', [\App\Http\Controllers\Inventory\FastOpeningController::class, 'index'])
        ->name('item.fast.opening');
    Route::post('/item-fast-opening', [\App\Http\Controllers\Inventory\FastOpeningController::class, 'store'])
        ->name('fast-opening.store');
    Route::get('/item-barcode-print', BarcodePrintController::class)
        ->name('item.barcode.print');

    Route::get('/purchases/open-bills', [\App\Http\Controllers\Purchase\PurchaseController::class, 'openBills'])->name('purchases.open-bills');
    Route::get('/purchases/list-export', [\App\Http\Controllers\Purchase\PurchaseController::class, 'exportList'])->name('purchases.list-export');
    Route::resource('/purchases', \App\Http\Controllers\Purchase\PurchaseController::class);
    Route::post('/purchases/{purchase}/post', [\App\Http\Controllers\Purchase\PurchaseController::class, 'post'])->name('purchases.post');
    Route::post('/purchases/{purchase}/reverse', [\App\Http\Controllers\Purchase\PurchaseController::class, 'reverse'])->name('purchases.reverse');
    Route::patch('/update-purchase-status/{purchase}/status', [\App\Http\Controllers\Purchase\PurchaseController::class, 'updatePurchaseStatus'])->name('purchases.update-purchase-status');
    Route::patch('/purchases/{purchase}/restore', [\App\Http\Controllers\Purchase\PurchaseController::class, 'restore'])->name('purchases.restore')->withTrashed();
    Route::delete('/purchases/{purchase}/force-delete', [\App\Http\Controllers\Purchase\PurchaseController::class, 'forceDelete'])
        ->name('purchases.force-delete')
        ->withTrashed();
    //    Route::post('item_entry/Store', ['as' => 'item_entry.store', 'uses' => 'FastEntry\ItemEntryController@store'])->middleware(['dbconfig','auth:sanctum']);
    Route::get('/purchase-item-change', [NextController::class, 'purchaseItemChange'])->name('purchase.item.change');

    Route::get('/sales/open-bills', [\App\Http\Controllers\Sale\SaleController::class, 'openBills'])->name('sales.open-bills');
    Route::get('/sales/list-export', [\App\Http\Controllers\Sale\SaleController::class, 'exportList'])->name('sales.list-export');
    Route::resource('/sales', \App\Http\Controllers\Sale\SaleController::class);
    Route::post('/sales/{sale}/post', [\App\Http\Controllers\Sale\SaleController::class, 'post'])->name('sales.post');
    Route::post('/sales/{sale}/reverse', [\App\Http\Controllers\Sale\SaleController::class, 'reverse'])->name('sales.reverse');
    Route::patch('/update-sale-status/{sale}/status', [\App\Http\Controllers\Sale\SaleController::class, 'updateSaleStatus'])->name('sales.update-sale-status');
    Route::patch('/sales/{sale}/restore', [\App\Http\Controllers\Sale\SaleController::class, 'restore'])->name('sales.restore')->withTrashed();
    Route::delete('/sales/{sale}/force-delete', [\App\Http\Controllers\Sale\SaleController::class, 'forceDelete'])
        ->name('sales.force-delete')
        ->withTrashed();
    Route::get('/item-with-batches', [NextController::class, 'getItemWithBatches'])->name('item.with.batches');
    Route::get('/sales/{sale}/print', [\App\Http\Controllers\Sale\SaleController::class, 'print'])->name('sales.print');
    Route::get('/sales/{sale}/export', [\App\Http\Controllers\Sale\SaleController::class, 'exportDetail'])->name('sales.export');
    Route::get('/purchases/{purchase}/export', [\App\Http\Controllers\Purchase\PurchaseController::class, 'exportDetail'])->name('purchases.export');

    Route::get('/sale-returns/returnable-items', [\App\Http\Controllers\Sale\SaleReturnController::class, 'returnableItems'])->name('sale-returns.returnable-items');
    Route::resource('/sale-returns', \App\Http\Controllers\Sale\SaleReturnController::class);
    Route::post('/sale-returns/{sale_return}/post', [\App\Http\Controllers\Sale\SaleReturnController::class, 'post'])->name('sale-returns.post');
    Route::post('/sale-returns/{sale_return}/reverse', [\App\Http\Controllers\Sale\SaleReturnController::class, 'reverse'])->name('sale-returns.reverse');
    Route::patch('/sale-returns/{sale_return}/restore', [\App\Http\Controllers\Sale\SaleReturnController::class, 'restore'])->name('sale-returns.restore')->withTrashed();
    Route::delete('/sale-returns/{sale_return}/force-delete', [\App\Http\Controllers\Sale\SaleReturnController::class, 'forceDelete'])
        ->name('sale-returns.force-delete')
        ->withTrashed();

    Route::get('/purchase-returns/returnable-items', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'returnableItems'])->name('purchase-returns.returnable-items');
    Route::resource('/purchase-returns', \App\Http\Controllers\Purchase\PurchaseReturnController::class);
    Route::post('/purchase-returns/{purchase_return}/post', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'post'])->name('purchase-returns.post');
    Route::post('/purchase-returns/{purchase_return}/reverse', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'reverse'])->name('purchase-returns.reverse');
    Route::patch('/purchase-returns/{purchase_return}/restore', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'restore'])->name('purchase-returns.restore')->withTrashed();
    Route::delete('/purchase-returns/{purchase_return}/force-delete', [\App\Http\Controllers\Purchase\PurchaseReturnController::class, 'forceDelete'])
        ->name('purchase-returns.force-delete')
        ->withTrashed();

    Route::get('/purchase-orders/eligible', [\App\Http\Controllers\Purchase\PurchaseOrderController::class, 'eligibleForLedger'])->name('purchase-orders.eligible');
    Route::get('/purchase-orders/{purchase_order}/for-conversion', [\App\Http\Controllers\Purchase\PurchaseOrderController::class, 'forConversion'])->name('purchase-orders.for-conversion');
    Route::resource('/purchase-orders', \App\Http\Controllers\Purchase\PurchaseOrderController::class);
    Route::post('/purchase-orders/{purchase_order}/post', [\App\Http\Controllers\Purchase\PurchaseOrderController::class, 'post'])->name('purchase-orders.post');
    Route::post('/purchase-orders/{purchase_order}/cancel', [\App\Http\Controllers\Purchase\PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    Route::patch('/purchase-orders/{purchase_order}/restore', [\App\Http\Controllers\Purchase\PurchaseOrderController::class, 'restore'])->name('purchase-orders.restore')->withTrashed();
    Route::delete('/purchase-orders/{purchase_order}/force-delete', [\App\Http\Controllers\Purchase\PurchaseOrderController::class, 'forceDelete'])
        ->name('purchase-orders.force-delete')
        ->withTrashed();

    Route::get('/sale-orders/eligible', [\App\Http\Controllers\Sale\SaleOrderController::class, 'eligibleForLedger'])->name('sale-orders.eligible');
    Route::get('/sale-orders/{sale_order}/for-conversion', [\App\Http\Controllers\Sale\SaleOrderController::class, 'forConversion'])->name('sale-orders.for-conversion');
    Route::resource('/sale-orders', \App\Http\Controllers\Sale\SaleOrderController::class);
    Route::post('/sale-orders/{sale_order}/post', [\App\Http\Controllers\Sale\SaleOrderController::class, 'post'])->name('sale-orders.post');
    Route::post('/sale-orders/{sale_order}/cancel', [\App\Http\Controllers\Sale\SaleOrderController::class, 'cancel'])->name('sale-orders.cancel');
    Route::patch('/sale-orders/{sale_order}/restore', [\App\Http\Controllers\Sale\SaleOrderController::class, 'restore'])->name('sale-orders.restore')->withTrashed();
    Route::delete('/sale-orders/{sale_order}/force-delete', [\App\Http\Controllers\Sale\SaleOrderController::class, 'forceDelete'])
        ->name('sale-orders.force-delete')
        ->withTrashed();

    // Purchase Quotations (standalone quotation documents)
    Route::get('/purchase-quotations/{purchase_quotation}/print', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'print'])->name('purchase-quotations.print');
    Route::resource('/purchase-quotations', \App\Http\Controllers\Purchase\PurchaseQuotationController::class);
    Route::post('/purchase-quotations/{purchase_quotation}/post', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'post'])->name('purchase-quotations.post');
    Route::post('/purchase-quotations/{purchase_quotation}/cancel', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'cancel'])->name('purchase-quotations.cancel');
    Route::patch('/purchase-quotations/{purchase_quotation}/restore', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'restore'])->name('purchase-quotations.restore')->withTrashed();
    Route::delete('/purchase-quotations/{purchase_quotation}/force-delete', [\App\Http\Controllers\Purchase\PurchaseQuotationController::class, 'forceDelete'])
        ->name('purchase-quotations.force-delete')
        ->withTrashed();

    // Sale Quotations (standalone quotation documents)
    Route::get('/sale-quotations/{sale_quotation}/print', [\App\Http\Controllers\Sale\SaleQuotationController::class, 'print'])->name('sale-quotations.print');
    Route::resource('/sale-quotations', \App\Http\Controllers\Sale\SaleQuotationController::class);
    Route::post('/sale-quotations/{sale_quotation}/post', [\App\Http\Controllers\Sale\SaleQuotationController::class, 'post'])->name('sale-quotations.post');
    Route::post('/sale-quotations/{sale_quotation}/cancel', [\App\Http\Controllers\Sale\SaleQuotationController::class, 'cancel'])->name('sale-quotations.cancel');
    Route::patch('/sale-quotations/{sale_quotation}/restore', [\App\Http\Controllers\Sale\SaleQuotationController::class, 'restore'])->name('sale-quotations.restore')->withTrashed();
    Route::delete('/sale-quotations/{sale_quotation}/force-delete', [\App\Http\Controllers\Sale\SaleQuotationController::class, 'forceDelete'])
        ->name('sale-quotations.force-delete')
        ->withTrashed();


    // Company routes
    Route::get('/company', [\App\Http\Controllers\CompanyController::class, 'show'])
        ->name('company.show');
    Route::put('/company/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])
        ->name('company.update');
    Route::patch('/company/{company}/restore', [\App\Http\Controllers\CompanyController::class, 'restore'])->name('company.restore')->withTrashed();

    // Search routes
    Route::post('/search/{resourceType}', [\App\Http\Controllers\SearchController::class, 'search']);
    Route::get('/search/resource-types', [\App\Http\Controllers\SearchController::class, 'getResourceTypes']);
    Route::get('/quick-create/items/next-code', [QuickCreateController::class, 'nextItemCode'])->name('quick-create.items.next-code');
    Route::post('/quick-create/{resourceType}', [QuickCreateController::class, 'store'])->name('quick-create.store');

    // Receipts
    Route::get('/receipts/export', [\App\Http\Controllers\Receipt\ReceiptController::class, 'export'])->name('receipts.export');
    Route::resource('/receipts', \App\Http\Controllers\Receipt\ReceiptController::class);
    Route::post('/receipts/{receipt}/post', [\App\Http\Controllers\Receipt\ReceiptController::class, 'post'])->name('receipts.post');
    Route::post('/receipts/{receipt}/reverse', [\App\Http\Controllers\Receipt\ReceiptController::class, 'reverse'])->name('receipts.reverse');
    Route::patch('/receipts/{receipt}/restore', [\App\Http\Controllers\Receipt\ReceiptController::class, 'restore'])->name('receipts.restore')->withTrashed();
    Route::delete('/receipts/{receipt}/force-delete', [\App\Http\Controllers\Receipt\ReceiptController::class, 'forceDelete'])
        ->name('receipts.force-delete')
        ->withTrashed();
    Route::get('/receipts/{receipt}/print', [\App\Http\Controllers\Receipt\ReceiptController::class, 'print'])->name('receipts.print');

    // Payments
    Route::get('/payments/export', [\App\Http\Controllers\Payment\PaymentController::class, 'export'])->name('payments.export');
    Route::resource('/payments', \App\Http\Controllers\Payment\PaymentController::class);
    Route::post('/payments/{payment}/post', [\App\Http\Controllers\Payment\PaymentController::class, 'post'])->name('payments.post');
    Route::post('/payments/{payment}/reverse', [\App\Http\Controllers\Payment\PaymentController::class, 'reverse'])->name('payments.reverse');
    Route::patch('/payments/{payment}/restore', [\App\Http\Controllers\Payment\PaymentController::class, 'restore'])->name('payments.restore')->withTrashed();
    Route::delete('/payments/{payment}/force-delete', [\App\Http\Controllers\Payment\PaymentController::class, 'forceDelete'])
        ->name('payments.force-delete')
        ->withTrashed();
    Route::get('/payments/{payment}/print', [\App\Http\Controllers\Payment\PaymentController::class, 'print'])->name('payments.print');

    // Attachments (polymorphic, shared across modules)
    Route::delete('/attachments/{attachment}', [\App\Http\Controllers\AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Account Transfers
    Route::get('/account-transfers/export', [\App\Http\Controllers\AccountTransfer\AccountTransferController::class, 'export'])->name('account-transfers.export');
    Route::resource('/account-transfers', \App\Http\Controllers\AccountTransfer\AccountTransferController::class);
    Route::post('/account-transfers/{accountTransfer}/post', [\App\Http\Controllers\AccountTransfer\AccountTransferController::class, 'post'])->name('account-transfers.post');
    Route::post('/account-transfers/{accountTransfer}/reverse', [\App\Http\Controllers\AccountTransfer\AccountTransferController::class, 'reverse'])->name('account-transfers.reverse');
    Route::patch('/account-transfers/{accountTransfer}/restore', [\App\Http\Controllers\AccountTransfer\AccountTransferController::class, 'restore'])->name('account-transfers.restore')->withTrashed();
    Route::delete('/account-transfers/{accountTransfer}/force-delete', [\App\Http\Controllers\AccountTransfer\AccountTransferController::class, 'forceDelete'])
        ->name('account-transfers.force-delete')
        ->withTrashed();

    // Item Inventory Modal
    Route::get('/items/{item}/in-records', [\App\Http\Controllers\Inventory\ItemController::class, 'inRecords'])->name('items.in-records');
    Route::get('/items/{item}/in-records/export', [\App\Http\Controllers\Inventory\ItemController::class, 'exportInRecords'])->name('items.in-records.export');
    Route::get('/items/{item}/out-records', [\App\Http\Controllers\Inventory\ItemController::class, 'outRecords'])->name('items.out-records');
    Route::get('/items/{item}/out-records/export', [\App\Http\Controllers\Inventory\ItemController::class, 'exportOutRecords'])->name('items.out-records.export');

    // Owners
    Route::get('/owners/export', [\App\Http\Controllers\Owner\OwnerController::class, 'export'])->name('owners.export');
    Route::resource('/owners', \App\Http\Controllers\Owner\OwnerController::class);
    Route::patch('/owners/{owner}/restore', [\App\Http\Controllers\Owner\OwnerController::class, 'restore'])->name('owners.restore')->withTrashed();
    Route::delete('/owners/{owner}/force-delete', [\App\Http\Controllers\Owner\OwnerController::class, 'forceDelete'])
        ->name('owners.force-delete')
        ->withTrashed();
    Route::get('/drawings/export', [\App\Http\Controllers\Owner\DrawingController::class, 'export'])->name('drawings.export');
    Route::resource('/drawings', \App\Http\Controllers\Owner\DrawingController::class);
    Route::post('/drawings/{drawing}/post', [\App\Http\Controllers\Owner\DrawingController::class, 'post'])->name('drawings.post');
    Route::post('/drawings/{drawing}/reverse', [\App\Http\Controllers\Owner\DrawingController::class, 'reverse'])->name('drawings.reverse');
    Route::patch('/drawings/{drawing}/restore', [\App\Http\Controllers\Owner\DrawingController::class, 'restore'])->name('drawings.restore')->withTrashed();
    Route::delete('/drawings/{drawing}/force-delete', [\App\Http\Controllers\Owner\DrawingController::class, 'forceDelete'])
        ->name('drawings.force-delete')
        ->withTrashed();

    // User Management
    Route::resource('/users', \App\Http\Controllers\UserManagement\UserController::class);
    Route::patch('/users/{user}/restore', [\App\Http\Controllers\UserManagement\UserController::class, 'restore'])->name('users.restore')->withTrashed();
    Route::delete('/users/{user}/force-delete', [\App\Http\Controllers\UserManagement\UserController::class, 'forceDelete'])
        ->name('users.force-delete')
        ->withTrashed();
    Route::resource('/roles', \App\Http\Controllers\UserManagement\RoleController::class);
    Route::patch('/roles/{role}/restore', [\App\Http\Controllers\UserManagement\RoleController::class, 'restore'])->name('roles.restore')->withTrashed();

    // Settings
    Route::get('/preferences', [\App\Http\Controllers\Preferences\PreferencesController::class, 'index'])->name('preferences.index');
    Route::put('/preferences', [\App\Http\Controllers\Preferences\PreferencesController::class, 'update'])->name('preferences.update');
    Route::post('/preferences/reset/{category?}', [\App\Http\Controllers\Preferences\PreferencesController::class, 'resetPreferences'])->name('preferences.reset');
    Route::get('/preferences/export', [\App\Http\Controllers\Preferences\PreferencesController::class, 'exportPreferences'])->name('preferences.export');
    Route::post('/preferences/import', [\App\Http\Controllers\Preferences\PreferencesController::class, 'importPreferences'])->name('preferences.import');
    Route::put('/preferences/install-plugins', [\App\Http\Controllers\Preferences\PreferencesController::class, 'updateInstallPlugins'])->name('preferences.install-plugins.update');

    // Invoice Formats (custom designer)
    Route::get('/invoice-formats', [\App\Http\Controllers\Sale\InvoiceFormatController::class, 'index'])->name('invoice-formats.index');
    Route::post('/invoice-formats', [\App\Http\Controllers\Sale\InvoiceFormatController::class, 'store'])->name('invoice-formats.store');
    Route::put('/invoice-formats/{invoiceFormat}', [\App\Http\Controllers\Sale\InvoiceFormatController::class, 'update'])->name('invoice-formats.update');
    Route::delete('/invoice-formats/{invoiceFormat}', [\App\Http\Controllers\Sale\InvoiceFormatController::class, 'destroy'])->name('invoice-formats.destroy');
    Route::patch('/invoice-formats/{invoiceFormat}/set-default', [\App\Http\Controllers\Sale\InvoiceFormatController::class, 'setDefault'])->name('invoice-formats.set-default');
    Route::post('/invoice-formats/{invoiceFormat}/clone', [\App\Http\Controllers\Sale\InvoiceFormatController::class, 'clone'])->name('invoice-formats.clone');

    // Expense Categories
    Route::resource('/expense-categories', \App\Http\Controllers\Expense\ExpenseCategoryController::class);
    Route::patch('/expense-categories/{expenseCategory}/restore', [\App\Http\Controllers\Expense\ExpenseCategoryController::class, 'restore'])->name('expense-categories.restore')->withTrashed();
    Route::delete('/expense-categories/{expenseCategory}/force-delete', [\App\Http\Controllers\Expense\ExpenseCategoryController::class, 'forceDelete'])
        ->name('expense-categories.force-delete')
        ->withTrashed();

    // Expenses
    Route::get('/expenses/export', [\App\Http\Controllers\Expense\ExpenseController::class, 'export'])->name('expenses.export');
    Route::resource('/expenses', \App\Http\Controllers\Expense\ExpenseController::class);
    Route::post('/expenses/{expense}/post', [\App\Http\Controllers\Expense\ExpenseController::class, 'post'])->name('expenses.post');
    Route::post('/expenses/{expense}/reverse', [\App\Http\Controllers\Expense\ExpenseController::class, 'reverse'])->name('expenses.reverse');
    Route::patch('/expenses/{expense}/restore', [\App\Http\Controllers\Expense\ExpenseController::class, 'restore'])->name('expenses.restore')->withTrashed();
    Route::delete('/expenses/{expense}/force-delete', [\App\Http\Controllers\Expense\ExpenseController::class, 'forceDelete'])
        ->name('expenses.force-delete')
        ->withTrashed();

    // Item Transfers
    Route::get('/item-transfers/export', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'export'])->name('item-transfers.export');
    Route::resource('/item-transfers', \App\Http\Controllers\ItemTransfer\ItemTransferController::class);
    Route::post('/item-transfers/{itemTransfer}/post', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'post'])->name('item-transfers.post');
    Route::post('/item-transfers/{itemTransfer}/reverse', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'reverse'])->name('item-transfers.reverse');
    Route::patch('/item-transfers/{itemTransfer}/restore', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'restore'])->name('item-transfers.restore')->withTrashed();
    Route::delete('/item-transfers/{itemTransfer}/force-delete', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'forceDelete'])
        ->name('item-transfers.force-delete')
        ->withTrashed();
    Route::patch('/item-transfers/{itemTransfer}/complete', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'complete'])->name('item-transfers.complete');
    Route::patch('/item-transfers/{itemTransfer}/cancel', [\App\Http\Controllers\ItemTransfer\ItemTransferController::class, 'cancel'])->name('item-transfers.cancel');
    // Journal Entries
    Route::get('/journal-entries/export', [\App\Http\Controllers\JournalEntry\JournalEntryController::class, 'export'])->name('journal-entries.export');
    Route::resource('/journal-entries', \App\Http\Controllers\JournalEntry\JournalEntryController::class);
    Route::post('/journal-entries/{journalEntry}/post', [\App\Http\Controllers\JournalEntry\JournalEntryController::class, 'post'])->name('journal-entries.post');
    Route::post('/journal-entries/{journalEntry}/reverse', [\App\Http\Controllers\JournalEntry\JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');
    Route::patch('/journal-entries/{journalEntry}/restore', [\App\Http\Controllers\JournalEntry\JournalEntryController::class, 'restore'])->name('journal-entries.restore')->withTrashed();
    Route::delete('/journal-entries/{journalEntry}/force-delete', [\App\Http\Controllers\JournalEntry\JournalEntryController::class, 'forceDelete'])
        ->name('journal-entries.force-delete')
        ->withTrashed();
    Route::resource('/journal-classes', \App\Http\Controllers\JournalEntry\JournalClassController::class);
    Route::patch('/journal-classes/{journalClass}/restore', [\App\Http\Controllers\JournalEntry\JournalClassController::class, 'restore'])->name('journal-classes.restore')->withTrashed();
    Route::delete('/journal-classes/{journalClass}/force-delete', [\App\Http\Controllers\JournalEntry\JournalClassController::class, 'forceDelete'])
        ->name('journal-classes.force-delete')
        ->withTrashed();
    Route::match(['get', 'post'], '/search/items-list', [SearchController::class, 'searchItemsList'])
        ->name('search.items-list');
    Route::get('/search/global', [SearchController::class, 'globalIndex'])
        ->name('search.global');
    Route::get('/search/suggestions', [SearchController::class, 'suggestions'])
        ->name('search.suggestions');
    Route::get('/search/{resourceType}', [SearchController::class, 'search']);
    Route::get('/search/resource-types', [SearchController::class, 'getResourceTypes']);

    // Branch switching (super-admin only)
    Route::post('/switch-branch', SwitchBranchController::class)
        ->name('branches.switch');

});
