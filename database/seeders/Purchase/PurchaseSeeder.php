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
            PurchaseItem::factory()->count(rand(2, 5))->create([
                'purchase_id' => $purchase->id,
            ]);
        });
    }
}
