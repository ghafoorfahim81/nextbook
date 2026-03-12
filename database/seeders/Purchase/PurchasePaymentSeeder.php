<?php

namespace Database\Seeders;

use App\Models\PurchasePayment;
use Illuminate\Database\Seeder;

class PurchasePaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PurchasePayment::factory()->count(5)->create();
    }
}
