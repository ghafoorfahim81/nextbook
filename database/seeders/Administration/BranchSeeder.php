<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Branch::factory()->count(5)->create();
    }
}
