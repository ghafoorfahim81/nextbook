<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Company::factory()->count(5)->create();
    }
}
