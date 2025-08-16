<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Store::create([
            'name' =>'Main Store',
            'address' =>'Main store',
            'is_main' => true,
            'branch_id' => Branch::first()->id,
        ]);
    }
}
