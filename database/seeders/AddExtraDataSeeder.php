<?php

namespace Database\Seeders;

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
                'name' => 'Other Income',
                'local_name' => 'درآمد دیگر',
                'number' => '9060',
                'account_type_id' => \App\Models\Account\AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'slug' => 'other-income',
                'remark' => '',
                'is_main' => true,
            ],
            [
                'name' => 'Foreign Exchange Gain',
                'local_name' => 'سود تغیر ارز',
                'number' => '9070',
                'parent_id' => \App\Models\Account\Account::withoutGlobalScopes()->where('slug', 'other-income')->first()->id,
                'account_type_id' => \App\Models\Account\AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'slug' => 'fx-gain',
                'remark' => '',
                'is_main' => true,
            ],
            [
                'name' => 'Foreign Exchange Loss',
                'local_name' => 'ضرر تغیر ارز',
                'number' => '9080',
                'parent_id' => \App\Models\Account\Account::withoutGlobalScopes()->where('slug', 'other-expenses')->first()->id,
                'account_type_id' => \App\Models\Account\AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'slug' => 'fx-loss',
                'remark' => '',
                'is_main' => true,
            ],
        ];

        foreach ($accounts as $account) {
             $exists = \App\Models\Account\Account::withoutGlobalScopes()
                ->where('number', $account['number'])
                ->exists();
            if ($exists) {
                continue;
            }
            \App\Models\Account\Account::create($account);
        }
    }
}
