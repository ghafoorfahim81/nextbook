<?php

namespace Database\Seeders\Account;

use App\Models\Account\AccountType;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Administration\Branch;
use Illuminate\Support\Str;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountTypes = AccountType::defaultAccountTypes();
        foreach ($accountTypes as $accountType) {
            AccountType::create([
                'id' => Str::uuid(),
                'name' => $accountType['name'],
                'is_main' => $accountType['is_main'],
                'slug' => $accountType['slug'],
                'remark' => $accountType['remark'],
                'branch_id' => Branch::where('is_main', true)->first()->id,
                'created_by' => User::first()->id,
            ]);
        }
    }
}
