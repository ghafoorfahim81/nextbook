<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $exists = Warehouse::withoutGlobalScopes()
            ->where('name', 'گدام مرکزی')
            ->exists();
        if ($exists) {
            return;
        }
        Warehouse::create([
            'name' => 'گدام مرکزی',
            'address' => 'گدام مرکزی',
            'is_main' => true,
            'is_active' => false,
            'branch_id' => Branch::first()->id,
        ]);
    }
}

