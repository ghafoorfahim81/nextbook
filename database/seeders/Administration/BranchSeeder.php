<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Services\BranchProvisioningService;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The main branch belongs to the default company, which CompanySeeder
     * creates before this runs. Failing loudly here is deliberate: a branch
     * with no company is the "unknown scope" case the architecture plan
     * rejects, and the column is not nullable.
     */
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()->where('name_en', 'NextBook')->first();

        if (! $company) {
            throw new \RuntimeException('No company found. Run CompanySeeder before BranchSeeder.');
        }

        $found = Branch::where('company_id', $company->id)
            ->where('name', 'Main Branch')
            ->first();

        if ($found) {
            app(BranchProvisioningService::class)->provision($found);

            return;
        }

        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'is_main' => true,
            'location' => 'Main Location',
            'remark' => 'Main Branch',
        ]);

        app(BranchProvisioningService::class)->provision($branch);
    }
}
