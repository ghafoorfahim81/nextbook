<?php

namespace Database\Seeders\Purchase;

use App\Models\Purchase\PurchaseItem;
use Illuminate\Database\Seeder;

class PurchaseItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PurchaseItem::factory()->count(20)->create();
    }
}
