<?php

namespace Database\Seeders\Account;

use App\Models\Account\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Account::factory()->count(5)->create();
    }
}
