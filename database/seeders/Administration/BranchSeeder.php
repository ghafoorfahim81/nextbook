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
        // Create main branch first
        $mainBranch = Branch::factory()->create([
            'name' => 'Main Branch',
            'is_main' => true,
            'sub_domain' => 'main',
            'location' => 'Main Location',
            'remark' => 'Main Branch'
        ]);

        // Create additional branches
        Branch::factory()->count(4)->create();
    }
}
