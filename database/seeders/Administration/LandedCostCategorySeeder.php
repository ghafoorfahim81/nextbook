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
            $existing = LandedCostCategory::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->where('name', $category['name'])
                ->first();

            // Re-running the seeder must not duplicate rows, but it should fill in
            // the local name for categories that predate the column.
            if ($existing) {
                if (blank($existing->local_name) && filled($category['local_name'] ?? null)) {
                    $existing->forceFill(['local_name' => $category['local_name']])->save();
                }

                continue;
            }

            LandedCostCategory::create([
                'name' => $category['name'],
                'local_name' => $category['local_name'] ?? null,
                'remark' => $category['remark'],
                'branch_id' => $branch->id,
                'created_by' => $createdBy,
            ]);
        }
    }
}
