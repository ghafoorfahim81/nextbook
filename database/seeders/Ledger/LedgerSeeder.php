<?php

namespace Database\Seeders\Ledger;

use App\Models\Ledger\Ledger;
use App\Models\User;
use App\Models\Administration\Branch;
use App\Models\Administration\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
class LedgerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ledger::factory()->count(5)->create();
        $ledgers = [
            [
                'id' => Str::ulid(),
                'name' => 'Cash customer',
                'code' => 'CASH-CUST',
                'type' => 'customer',
                'created_by' => User::withoutGlobalScopes()->where('email', 'admin@nextbook.com')->first()->id,
            ],
        ];
        Ledger::insert($ledgers);
    }
}
