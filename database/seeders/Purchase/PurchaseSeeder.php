<?php

namespace Database\Seeders\Purchase;

use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use Illuminate\Database\Seeder;

class PurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Purchase::factory()->count(5)->create()->each(function ($purchase) {
            // Create related purchase items
            PurchaseItem::factory()->count(rand(2, 15))->create([
                'purchase_id' => $purchase->id,
            ]);

            // Create and associate a related transaction
            $transaction = \App\Models\Transaction\Transaction::factory()->create([
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
            ]);

            // Optionally update the purchase with the transaction_id if such a field exists
            $purchase->transaction_id = $transaction->id;
            $purchase->save();
        });
    }
}
