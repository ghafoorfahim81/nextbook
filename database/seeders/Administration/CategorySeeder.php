<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Company;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Install the standard item category catalogue on the main branch.
     *
     * The English label goes in `name` and the Dari/Persian label in
     * `local_name`, so the UI can print either depending on the active locale.
     */
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()->first();

        if (! $company) {
            return;
        }

        $createdBy = User::where('email', 'admin@nextbook.af')->first()?->id ?? User::first()?->id;

        foreach (Category::defaultCategories() as $category) {
            $existing = Category::withoutGlobalScopes()
                ->where('company_id', $company->id)
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

            Category::create([
                'name' => $category['name'],
                'local_name' => $category['local_name'] ?? null,
                'remark' => $category['remark'] ?? null,
                'is_active' => true,
                'company_id' => $company->id,
                'created_by' => $createdBy,
            ]);
        }
    }
}
