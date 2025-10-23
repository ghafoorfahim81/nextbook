<?php
// app/Services/TransactionService.php

namespace App\Services;

use App\Models\Transaction\Transaction;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
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



    private function determinePurchaseType(string $purchaseType): string
    {
        // type mapping logic
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
        ])->validate();
    }
}
