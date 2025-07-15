<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::factory()->count(5)->create();
    }
}
