<?php
// app/Services/TransactionService.php

namespace App\Services;

use App\Models\Transaction\Transaction;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use Illuminate\Support\Facades\DB;

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



    // Private helper methods
    private function calculateNetAmount(float $totalAmount, $discount, $discountType): float
    {
        // calculation logic
    }

    private function determinePurchaseType(string $purchaseType): string
    {
        // type mapping logic
    }
}
