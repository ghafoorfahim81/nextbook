<?php

namespace Database\Seeders\Purchase;

use App\Models\Purchase\Purchase;
use Illuminate\Database\Seeder;

class PurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Purchase::factory()->count(5)->create();
    }
}
