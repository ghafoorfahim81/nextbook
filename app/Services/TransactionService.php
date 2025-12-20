<?php
// app/Services/TransactionService.php

namespace App\Services;

use App\Models\Transaction\Transaction;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Models\Expense\Expense;
use App\Models\Ledger\Ledger;
use Illuminate\Support\Facades\DB;
use App\Models\Account\Account;
use Illuminate\Support\Facades\Cache;
class TransactionService
{
    // Core method - used directly for maximum performance
    public function createTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $validatedData = $this->validateTransactionData($data);
            return Transaction::create($validatedData);
        });
    }

    public function createPurchaseTransactions(Purchase $purchase, Ledger $ledger, float $transactionTotal, string $transactionType, $payment, $currency_id, $rate)
    {
        $transactions = [];

        // ALWAYS: DEBIT Inventory (Inventory comes IN)
        $inventoryTransaction = $this->createTransaction([
            'account_id' => Account::where('slug', 'inventory-asset')->first()->id,
            'ledger_id' => $ledger->id,
            'amount' => $transactionTotal,
            'currency_id' => $currency_id,
            'rate' => $rate,
            'date' => $purchase->date,
            'type' => 'debit',
            'remark' => "Purchase #{$purchase->number} from {$ledger->name}",
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
        ]);

        // CONDITION: CREDIT based on payment method
        if ($transactionType === 'credit') {
            $payableTransaction = $this->createTransaction([
                'account_id' => $payment['account_id'],
                'ledger_id' => $ledger->id,
                'amount' => $payment['amount'],
                'currency_id' => $currency_id,
                'rate' => $rate,
                'date' => $purchase->date,
                'type' => 'credit',
                'remark' => $payment['note'],
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
            ]);
        } else {
            // Cash purchase
            $cashTransaction = $this->createTransaction([
                'account_id' => Account::where('slug', 'cash-in-hand')->first()->id,
                'ledger_id' => $ledger->id,
                'amount' => $transactionTotal,
                'currency_id' => $currency_id,
                'rate' => $rate,
                'date' => $purchase->date,
                'type' => 'credit',
                'remark' => "Cash payment for purchase #{$purchase->number}",
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
            ]);
        }
        $purchase->update(['transaction_id' => $inventoryTransaction->id]);

        Cache::forget('ledgers');



        return $transactions;
    }

    public function updatePurchaseTransactions(Purchase $purchase, Ledger $ledger, float $transactionTotal, string $transactionType, $payment, $currency_id, $rate)
    {
        return DB::transaction(function () use ($purchase, $ledger, $transactionTotal, $transactionType, $payment, $currency_id, $rate) {
            // Find existing transactions for this purchase
            $existingTransactions = Transaction::where('reference_type', 'purchase')
                ->where('reference_id', $purchase->id)
                ->get();

            if ($existingTransactions->isEmpty()) {
                // If no existing transactions, create new ones
                return $this->createPurchaseTransactions($purchase, $ledger, $transactionTotal, $transactionType, $payment, $currency_id, $rate);
            }

            // Update existing inventory transaction
            $inventoryTransaction = $existingTransactions->where('type', 'debit')->first();
            if ($inventoryTransaction) {
                $inventoryTransaction->update([
                    'account_id' => Account::where('slug', 'inventory-asset')->first()->id,
                    'ledger_id' => $ledger->id,
                    'amount' => $transactionTotal,
                    'currency_id' => $currency_id,
                    'rate' => $rate,
                    'date' => $purchase->date,
                    'remark' => "Purchase #{$purchase->number} from {$ledger->name}",
                ]);
            }

            // Handle payment transaction updates
            if ($transactionType === 'credit' && $payment) {
                // For credit purchases, update or create payment transaction
                $payableTransaction = $existingTransactions->where('type', 'credit')->where('account_id', $payment['account_id'])->first();
                if ($payableTransaction) {
                    $payableTransaction->update([
                        'ledger_id' => $ledger->id,
                        'amount' => $payment['amount'],
                        'currency_id' => $currency_id,
                        'rate' => $rate,
                        'date' => $purchase->date,
                        'remark' => $payment['note'],
                    ]);
                } else {
                    // Create new payment transaction if it doesn't exist
                    $this->createTransaction([
                        'account_id' => $payment['account_id'],
                        'ledger_id' => $ledger->id,
                        'amount' => $payment['amount'],
                        'currency_id' => $currency_id,
                        'rate' => $rate,
                        'date' => $purchase->date,
                        'type' => 'credit',
                        'remark' => $payment['note'],
                        'reference_type' => 'purchase',
                        'reference_id' => $purchase->id,
                    ]);
                }
            } else {
                // For cash purchases, update cash transaction
                $cashTransaction = $existingTransactions->where('type', 'credit')
                    ->where('account_id', Account::where('slug', 'cash-in-hand')->first()->id)
                    ->first();
                if ($cashTransaction) {
                    $cashTransaction->update([
                        'ledger_id' => $ledger->id,
                        'amount' => $transactionTotal,
                        'currency_id' => $currency_id,
                        'rate' => $rate,
                        'date' => $purchase->date,
                        'remark' => "Cash payment for purchase #{$purchase->number}",
                    ]);
                } else {
                    // Create new cash transaction if it doesn't exist
                    $this->createTransaction([
                        'account_id' => Account::where('slug', 'cash-in-hand')->first()->id,
                        'ledger_id' => $ledger->id,
                        'amount' => $transactionTotal,
                        'currency_id' => $currency_id,
                        'rate' => $rate,
                        'date' => $purchase->date,
                        'type' => 'credit',
                        'remark' => "Cash payment for purchase #{$purchase->number}",
                        'reference_type' => 'purchase',
                        'reference_id' => $purchase->id,
                    ]);
                }
            }

            Cache::tags(['ledgers', 'accounts'])->flush();
        });
    }

    public function createSaleTransactions(Sale $sale, Ledger $ledger, float $transactionTotal, string $transactionType, $payment, $currency_id, $rate)
    {
        $transactions = [];

        // ALWAYS: CREDIT Sales Revenue (Money comes IN)
        $glAccounts = Cache::get('gl_accounts');
        $salesTransaction = $this->createTransaction([
            'account_id' => $glAccounts['sales-revenue'],
            'ledger_id' => $ledger->id,
            'amount' => $transactionTotal,
            'currency_id' => $currency_id,
            'rate' => $rate,
            'date' => $sale->date,
            'type' => 'credit',
            'remark' => "Sale #{$sale->number} to {$ledger->name}",
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        // CONDITION: DEBIT based on payment method
        if ($transactionType === 'credit') {
            $receivableTransaction = $this->createTransaction([
                'account_id' => $payment['account_id'],
                'ledger_id' => $ledger->id,
                'amount' => $payment['amount'],
                'currency_id' => $currency_id,
                'rate' => $rate,
                'date' => $sale->date,
                'type' => 'debit',
                'remark' => $payment['note'],
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
            ]);
        } 
        
        // CONDITION: Loan based on payment method
        elseif ($transactionType === 'on_loan') {
            $loanTransaction = $this->createTransaction([
                'account_id' => $glAccounts['account-receivable'],
                'ledger_id' => $ledger->id,
                'amount' => $transactionTotal,
                'currency_id' => $currency_id,
                'rate' => $rate,
                'date' => $sale->date,
                'type' => 'debit',
                'remark' => "Loan receipt for sale #{$sale->number}",
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
            ]);
        }
        else {
            // Cash sale
            $cashAccountId = User::find(auth()->user()->id)->getPreference('sale_cash_account_id');
            $cashTransaction = $this->createTransaction([
                'account_id' => $cashAccountId,
                'ledger_id' => $ledger->id,
                'amount' => $transactionTotal,
                'currency_id' => $currency_id,
                'rate' => $rate,
                'date' => $sale->date,
                'type' => 'debit',
                'remark' => "Cash receipt for sale #{$sale->number}",
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
            ]);
        }
        
        

        // ALWAYS: DEBIT Cost of Goods Sold (COGS) - Inventory goes OUT
        $cogsTransaction = $this->createTransaction([
            'account_id' => $glAccounts['cost-of-goods-sold'],
            'ledger_id' => null,
            'amount' => $transactionTotal, // This should be the cost value, not selling price
            'currency_id' => $currency_id,
            'rate' => $rate,
            'date' => $sale->date,
            'type' => 'debit',
            'remark' => "COGS for sale #{$sale->number}",
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        // CREDIT Inventory (Inventory goes OUT)
        $inventoryTransaction = $this->createTransaction([
            'account_id' => $glAccounts['inventory-asset'],
            'ledger_id' => null,
            'amount' => $transactionTotal, // This should be the cost value, not selling price
            'currency_id' => $currency_id,
            'rate' => $rate,
            'date' => $sale->date,
            'type' => 'credit',
            'remark' => "Inventory reduction for sale #{$sale->number}",
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
        ]);

        $sale->update(['transaction_id' => $salesTransaction->id]);

        // Cache::forget('key''ledgers', 'accounts')->flush();

        return $transactions;
    }

    private function determinePurchaseType(string $purchaseType): string
    {
        // type mapping logic
        return $purchaseType;
    }

    /**
     * Create expense transactions (DR: Expense Account, CR: Bank/Cash Account)
     * 
     * @param Expense $expense The expense record
     * @param float $total Total expense amount
     * @param string $currencyId Currency ID
     * @param float $rate Exchange rate
     * @return array Array containing 'expense' and 'bank' transactions
     */
    public function createExpenseTransactions(Expense $expense, float $total, string $currencyId, float $rate, string $expenseAccountId, string $bankAccountId): array
    {
        return DB::transaction(function () use ($expense, $total, $currencyId, $rate, $expenseAccountId, $bankAccountId) {
            // Transaction 1: DEBIT Expense Account (expense increases)
            $expenseTransaction = $this->createTransaction([
                'account_id' => $expenseAccountId,
                'ledger_id' => null,
                'amount' => $total*$rate,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $expense->date,
                'type' => 'debit',
                'remark' => "Expense: {$expense->category->name} - {$expense->remarks}",
                'reference_type' => 'expense',
                'reference_id' => $expense->id,
            ]);

            // Transaction 2: CREDIT Bank/Cash Account (money goes out)
            $bankTransaction = $this->createTransaction([
                'account_id' => $bankAccountId,
                'ledger_id' => null,
                'amount' => $total*$rate,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'date' => $expense->date,
                'type' => 'credit',
                'remark' => "Payment for expense: {$expense->category->name}",
                'reference_type' => 'expense',
                'reference_id' => $expense->id,
            ]);

            Cache::forget('accounts');

            return [
                'expense' => $expenseTransaction,
                'bank' => $bankTransaction,
            ];
        });
    }

    /**
     * Update expense transactions
     */
    public function updateExpenseTransactions(Expense $expense, float $total, string $currencyId, float $rate): array
    {
        return DB::transaction(function () use ($expense, $total, $currencyId, $rate) {
            // Delete existing transactions
            Transaction::where('reference_type', 'expense')
                ->where('reference_id', $expense->id)
                ->forceDelete();

            // Create new transactions
            return $this->createExpenseTransactions($expense, $total, $currencyId, $rate);
        });
    }

    protected function validateTransactionData(array $data): array
    {
        return validator($data, [
            'account_id' => 'required|exists:accounts,id',
            'ledger_id' => 'nullable|exists:ledgers,id',
            'amount' => 'required|numeric|min:0',
            'currency_id' => 'required|exists:currencies,id',
            'rate' => 'required|numeric|min:0',
            'date' => 'required|date',
            'type' => 'required|string',
            'remark' => 'nullable|string',
            'reference_type' => 'nullable|string',
            'reference_id' => 'nullable|string',
        ])->validate();
    }
}
