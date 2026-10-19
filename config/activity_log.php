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
            \App\Models\Accounting\Settlement::class,
            \App\Models\Transaction\Transaction::class,

            // Human resources. Employee records and their contracts and
            // documents are low-volume and legally sensitive, so every change
            // is worth an audit row.
            //
            // Attendance is deliberately absent and must stay that way: a
            // single branch produces roughly 150k punch rows a year, and
            // logging each one would dwarf the rest of the audit trail.
            \App\Models\Hr\Employee::class,
            \App\Models\Hr\EmployeeContract::class,
            \App\Models\Hr\EmployeeDocument::class,
            \App\Models\Hr\Shift::class,
            \App\Models\Hr\Holiday::class,
            \App\Models\Hr\AttendanceDevice::class,
            \App\Models\Hr\LeaveType::class,
            \App\Models\Hr\LeaveAllocation::class,
            \App\Models\Hr\LeaveRequest::class,

            // Payroll. Salary figures and tax tables are the most sensitive
            // data in the system, and "who changed this bracket" is exactly
            // the question an audit gets asked.
            //
            // payroll_lines and payroll_line_components are absent on purpose:
            // they are rebuilt wholesale on every recalculation, so logging
            // them would record the engine's working rather than anyone's
            // decision. The run itself carries the decisions.
            \App\Models\Hr\SalaryComponent::class,
            \App\Models\Hr\SalaryStructure::class,
            \App\Models\Hr\TaxBracketSet::class,
            \App\Models\Hr\Payroll::class,
            \App\Models\Hr\SalaryPayment::class,
            \App\Models\Hr\EmployeeLoan::class,

            // Recruitment. Hiring decisions attract disputes, so the pipeline
            // is logged; interview feedback is part of that record.
            \App\Models\Hr\JobOpening::class,
            \App\Models\Hr\JobApplication::class,
            \App\Models\Hr\Interview::class,

            // Documents and master data that had no log at all. Each request is
            // written as a single entry (see BatchActivityLog), so covering them
            // no longer multiplies rows; where a controller also logs the
            // action explicitly, the two merge into one entry.
            \App\Models\Administration\Category::class,
            \App\Models\Administration\CustomerGroup::class,
            \App\Models\Administration\PaymentTerm::class,
            \App\Models\Administration\LandedCostCategory::class,
            \App\Models\Administration\CurrencyRateUpdate::class,
            \App\Models\Inventory\DiscountRule::class,
            \App\Models\Inventory\LandedCost::class,
            \App\Models\Inventory\StockAdjustment::class,
            \App\Models\ItemTransfer\ItemTransfer::class,
            \App\Models\JournalEntry\JournalEntry::class,
            \App\Models\Accounting\ContraSettlement::class,
            \App\Models\Expense\Expense::class,
            \App\Models\Owner\Owner::class,
            \App\Models\Owner\Drawing::class,
            \App\Models\Payment\Payment::class,
            \App\Models\Receipt\Receipt::class,
            \App\Models\Purchase\Purchase::class,
            \App\Models\Purchase\PurchaseOrder::class,
            \App\Models\Purchase\PurchaseQuotation::class,
            \App\Models\Purchase\PurchaseReturn::class,
            \App\Models\Sale\Sale::class,
            \App\Models\Sale\SaleOrder::class,
            \App\Models\Sale\SaleQuotation::class,
            \App\Models\Sale\SaleReturn::class,
            \App\Models\Sale\InvoiceFormat::class,
        ],

        /*
        | Rows that only mean something as part of their document: the lines of
        | a sale, an item's variants, a journal's debit/credit lines. They are
        | logged only inside a request, where they are attached to the
        | document's entry as related records — never as entries of their own.
        */
        'detail_models' => [
            \App\Models\Inventory\ItemVariant::class,
            \App\Models\Inventory\LandedCostItem::class,
            \App\Models\Inventory\StockAdjustmentItem::class,
            \App\Models\ItemTransfer\ItemTransferItem::class,
            \App\Models\Transaction\TransactionLine::class,
            \App\Models\Expense\ExpenseDetail::class,
            \App\Models\Purchase\PurchaseItem::class,
            \App\Models\Purchase\PurchaseOrderItem::class,
            \App\Models\Purchase\PurchaseQuotationItem::class,
            \App\Models\Purchase\PurchaseReturnItem::class,
            \App\Models\Sale\SaleItem::class,
            \App\Models\Sale\SaleOrderItem::class,
            \App\Models\Sale\SaleQuotationItem::class,
            \App\Models\Sale\SaleReturnItem::class,
            \App\Models\Hr\SalaryStructureLine::class,
            \App\Models\Hr\TaxBracket::class,
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

    /*
    | The child data that makes up each record's form, captured before and
    | after an edit so its old values are kept. Most updates replace these
    | rows with query-builder deletes and re-inserts, which fire no model
    | events — without the snapshot an edited opening balance or a changed
    | sale line left no trace of what it was before. Dots walk nested
    | relations (an account's opening → its transaction → the lines).
    */
    'snapshot_relations' => [
        \App\Models\Account\Account::class => ['opening.transaction.lines'],
        \App\Models\Ledger\Ledger::class => ['openings.transaction.lines'],
        \App\Models\Inventory\Item::class => ['variants', 'openings'],
        \App\Models\Sale\Sale::class => ['items', 'transaction.lines'],
        \App\Models\Sale\SaleReturn::class => ['items', 'transaction.lines'],
        \App\Models\Sale\SaleOrder::class => ['items'],
        \App\Models\Sale\SaleQuotation::class => ['items'],
        \App\Models\Purchase\Purchase::class => ['items', 'transaction.lines'],
        \App\Models\Purchase\PurchaseReturn::class => ['items', 'transaction.lines'],
        \App\Models\Purchase\PurchaseOrder::class => ['items'],
        \App\Models\Purchase\PurchaseQuotation::class => ['items'],
        \App\Models\Expense\Expense::class => ['details', 'transaction.lines'],
        \App\Models\Receipt\Receipt::class => ['transaction.lines', 'settlements'],
        \App\Models\Payment\Payment::class => ['transaction.lines', 'settlements'],
        \App\Models\JournalEntry\JournalEntry::class => ['transaction.lines'],
        \App\Models\AccountTransfer\AccountTransfer::class => ['transaction.lines'],
        \App\Models\Owner\Owner::class => ['transaction.lines'],
        \App\Models\Owner\Drawing::class => ['transaction.lines'],
        \App\Models\Inventory\LandedCost::class => ['items', 'categoryAllocations'],
        \App\Models\Inventory\StockAdjustment::class => ['items'],
        \App\Models\ItemTransfer\ItemTransfer::class => ['items'],
    ],

    /*
    | Route resources whose name does not singularise to their module, used to
    | pick which saved record heads a request's log entry.
    */
    'route_modules' => [
        'chart-of-accounts' => 'account',
        'item-fast-entry' => 'item',
        'item-fast-opening' => 'item',
        'customers' => 'ledger',
        'suppliers' => 'ledger',
        'discount-rules' => 'discount_rule',
    ],
];
