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
        $found = Branch::where('name', 'Main Branch')->first();
        if ($found) {
            return;
        }
        Branch::create([
            'name' => 'Main Branch',
            'is_main' => true,
            'sub_domain' => 'main',
            'location' => 'Main Location',
            'remark' => 'Main Branch',
            
        ]);
    }
}
