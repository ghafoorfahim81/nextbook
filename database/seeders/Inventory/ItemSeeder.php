<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Item::factory()->count(5)->create();
    }
}
