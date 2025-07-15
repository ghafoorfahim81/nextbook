<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Quantity;
use Illuminate\Database\Seeder;

class QuantitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Quantity::factory()->count(5)->create();
    }
}
