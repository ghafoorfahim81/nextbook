<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Account\AccountSeeder;
use Database\Seeders\Administration\BranchSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Administration\CategorySeeder;
use Database\Seeders\Administration\BrandSeeder;
use Database\Seeders\Administration\DepartmentSeeder;
use Database\Seeders\Administration\QuantitySeeder;
use Database\Seeders\Administration\StoreSeeder;
use Database\Seeders\Administration\UnitMeasureSeeder;
use Database\Seeders\Administration\UserSeeder;
use Database\Seeders\Administration\CompanySeeder;
use Database\Seeders\Account\AccountTypeSeeder;
use Database\Seeders\Administration\CurrencySeeder;
use Database\Seeders\Inventory\ItemSeeder;
use Database\Seeders\Ledger\LedgerSeeder;
use Database\Seeders\Purchase\PurchaseSeeder;
use Database\Seeders\Purchase\PurchaseItemSeeder;
use Database\Seeders\UserManagement\RolePermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Account\Account;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BranchSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(StoreSeeder::class);
        //        $this->call(QuantitySeeder::class);
        $this->call(UnitMeasureSeeder::class);
        $this->call(BrandSeeder::class);
        $this->call(AccountTypeSeeder::class);
        $this->call(CurrencySeeder::class);
        $this->call(LedgerSeeder::class);
        $this->call(ItemSeeder::class);
        $this->call(AccountSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->call(CompanySeeder::class);

        // $this->call(PurchaseSeeder::class);
        // $this->call(PurchaseItemSeeder::class);
    }
}
