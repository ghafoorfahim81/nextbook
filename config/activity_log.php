<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Observer-backed CRUD Coverage
    |--------------------------------------------------------------------------
    |
    | Explicit business action logs should remain the primary audit source for
    | critical ERP workflows. This registry adds a controlled CRUD fallback for
    | high-level business records so the system has wide coverage without
    | logging every technical child row.
    |
    */
    'observer' => [
        'models' => [
            \App\Models\User::class,
            \App\Models\Role::class,
            \App\Models\Permission::class,
            \App\Models\Account\Account::class,
            \App\Models\Account\AccountType::class,
            \App\Models\AccountTransfer\AccountTransfer::class,
            \App\Models\Administration\Branch::class,
            \App\Models\Administration\Brand::class,
            \App\Models\Administration\Company::class,
            \App\Models\Administration\Currency::class,
            \App\Models\Administration\Department::class,
            \App\Models\Administration\Designation::class,
            \App\Models\Administration\Quantity::class,
            \App\Models\Administration\Size::class,
            \App\Models\Administration\UnitMeasure::class,
            \App\Models\Administration\Warehouse::class,
            \App\Models\Expense\ExpenseCategory::class,
            \App\Models\Inventory\Item::class,
            \App\Models\Inventory\StockOpening::class,
            \App\Models\JournalEntry\JournalClass::class,
            \App\Models\Ledger\Ledger::class,
            \App\Models\Ledger\LedgerOpening::class,
            \App\Models\Purchase\PurchasePayment::class,
            \App\Models\Transaction\Transaction::class,
        ],
        'except_attributes' => [
            'created_at',
            'updated_at',
            'deleted_at',
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ],
    ],
];
