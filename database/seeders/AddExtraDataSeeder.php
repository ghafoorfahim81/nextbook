<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AddExtraDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // For running this seeder, you need to run the following command: php artisan db:seed --class=\Database\Seeders\AddExtraDataSeeder
        $accounts = [
            [
                'name' => 'Inventory Shrinkage & Wastage',
                'local_name' => 'ضایعات و کسری انبار',
                'number' => '9040',
                'account_type_id' => \App\Models\Account\AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id, 
                'slug' => 'inventory-shrinkage-and-wastage',
                'remark' => 'Inventory shrinkage and wastage',
                'is_main' => true,
            ],
            [
                'name' => 'Inventory Adjustments',
                'local_name' => 'تعدیلات موجودی',
                'number' => '9050',
                'account_type_id' => \App\Models\Account\AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id, 
                'slug' => 'inventory-adjustments',
                'remark' => 'Inventory adjustments',
                'is_main' => true,
            ],
        ];

        foreach ($accounts as $account) {
            $existingAccount = \App\Models\Account\Account::where('number', $account['number'])->first();
            if ($existingAccount) {
                continue;
            }
            \App\Models\Account\Account::create($account);
        }
    }
}
