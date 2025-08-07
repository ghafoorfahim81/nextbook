<?php

namespace Database\Seeders\Ledger;

use App\Models\Ledger\Ledger;
use Illuminate\Database\Seeder;

class LedgerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Ledger::factory()->count(5)->create();
    }
}
