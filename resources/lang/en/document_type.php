<?php

/**
 * What kind of document a journal entry came from.
 *
 * Keys are the snake_case basename of transactions.reference_type, derived by
 * App\Support\DocumentType. A type missing here falls back to its English
 * headline, so adding a document without translating it degrades to readable
 * English rather than to a raw key on screen.
 */
return [
    'journal' => 'Journal',
    'opening_balance' => 'Opening Balance',
    'reversal' => 'Reversal',

    'sale' => 'Sale',
    'sale_return' => 'Sale Return',
    'purchase' => 'Purchase',
    'purchase_return' => 'Purchase Return',

    'receipt' => 'Receipt',
    'payment' => 'Payment',
    'account_transfer' => 'Account Transfer',
    'contra_settlement' => 'Set-off',
    'journal_entry' => 'Journal Entry',

    'expense' => 'Expense',
    'drawing' => 'Drawing',
    'owner' => 'Owner',

    'item' => 'Item',
    'item_transfer' => 'Item Transfer',
    'stock_adjustment' => 'Stock Adjustment',

    'payroll' => 'Payroll',
    'salary_payment' => 'Salary Payment',
    'employee_loan' => 'Staff Loan',
    'employee_loan_repayment' => 'Loan Repayment',

    'account' => 'Account',
    'ledger' => 'Ledger',
    'transaction' => 'Transaction',
];
