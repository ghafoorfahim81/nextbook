<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\LandedCostCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class LandedCostCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::where('is_main', true)->first();

        if (! $branch) {
            return;
        }

        $createdBy = User::where('email', 'admin@nextbook.af')->first()?->id ?? User::first()?->id;

        foreach (LandedCostCategory::defaultCategories() as $category) {
            $exists = LandedCostCategory::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->where('name', $category['name'])
                ->exists();

            if ($exists) {
                continue;
            }

            LandedCostCategory::create([
                'name' => $category['name'],
                'remark' => $category['remark'],
                'branch_id' => $branch->id,
                'created_by' => $createdBy,
            ]);
        }
    }
}
