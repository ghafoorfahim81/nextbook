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
        // A warehouse is owned by the company and operated by a branch, so both
        // are set explicitly. Seeders run with no authenticated user, which is
        // what HasCompany would otherwise resolve the company from.
        $branch = Branch::withoutGlobalScopes()->firstOrFail();

        Warehouse::create([
            'name' => 'گدام مرکزی',
            'address' => 'گدام مرکزی',
            'is_main' => true,
            'is_active' => false,
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
        ]);
    }
}

