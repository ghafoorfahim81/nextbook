<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        Warehouse::create([
            'name' => 'Main Warehouse',
            'address' => 'Main warehouse',
            'is_main' => true,
            'is_active' => false,
            'branch_id' => Branch::first()->id,
        ]);
    }
}

