<?php

namespace Database\Seeders;
   
use Database\Seeders\Account\AccountSeeder;
use Database\Seeders\Administration\BranchSeeder;
use Database\Seeders\Administration\CategorySeeder;
use Database\Seeders\Administration\LandedCostCategorySeeder;
use Database\Seeders\Administration\SizeSeeder;
use Database\Seeders\Administration\WarehouseSeeder;
use Database\Seeders\Administration\UnitMeasureSeeder;
use Database\Seeders\Administration\UserSeeder;
use Database\Seeders\Administration\CompanySeeder;
use Database\Seeders\Account\AccountTypeSeeder;
use Database\Seeders\Administration\CurrencySeeder;
use Database\Seeders\Administration\ReferenceDataSeeder;
use Database\Seeders\Ledger\LedgerSeeder;
use Database\Seeders\UserManagement\RolePermissionSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {


        // CompanySeeder runs first: a branch cannot exist without a company,
        // and every table below is scoped to one. It is called a second time at
        // the end to fill in the company's home currency, which only exists
        // once CurrencySeeder has run — the same two-pass shape UserSeeder
        // already uses to pick up its branch.
        if(app()->environment('local')){
            $this->call(CompanySeeder::class);
            $this->call(UserSeeder::class);
            $this->call(BranchSeeder::class);
            $this->call(UserSeeder::class);
            $this->call(WarehouseSeeder::class);
            $this->call(UnitMeasureSeeder::class);
            // $this->call(BrandSeeder::class);
            $this->call(AccountTypeSeeder::class);
            $this->call(CurrencySeeder::class);
            $this->call(ReferenceDataSeeder::class);
            $this->call(LedgerSeeder::class);
            $this->call(AccountSeeder::class);
            // Before RolePermissionSeeder: this pass links the admin to the
            // company, and the admin's role is assigned per company.
            $this->call(CompanySeeder::class);
            $this->call(RolePermissionSeeder::class);
            $this->call(SizeSeeder::class);
            $this->call(CategorySeeder::class);
            $this->call(LandedCostCategorySeeder::class);
            // $this->call(ItemSeeder::class);
        }
        if(app()->environment('production')){
            $this->call(CompanySeeder::class);
            $this->call(UserSeeder::class);
            $this->call(BranchSeeder::class);
            $this->call(UserSeeder::class);
            $this->call(WarehouseSeeder::class);
            $this->call(CurrencySeeder::class);
            $this->call(ReferenceDataSeeder::class);
            $this->call(UnitMeasureSeeder::class);
            $this->call(LedgerSeeder::class);
            $this->call(AccountTypeSeeder::class);
            $this->call(AccountSeeder::class);
            $this->call(CompanySeeder::class);
            $this->call(RolePermissionSeeder::class);
            $this->call(SizeSeeder::class);
            $this->call(CategorySeeder::class);
            $this->call(LandedCostCategorySeeder::class);
        }
        // $this->call(PerformanceLoadSeeder::class);
    }
}
