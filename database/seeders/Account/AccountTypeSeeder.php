<?php

namespace Database\Seeders\Account;

use App\Models\Administration\Company;
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
            $exists = AccountType::withoutGlobalScopes()
                ->where('slug', $accountType['slug'])
                ->exists();
            if ($exists) {
                continue;
            }
            AccountType::create([
                'id' => Str::ulid(),
                'name' => $accountType['name'],
                'is_main' => $accountType['is_main'],
                'slug' => $accountType['slug'],
                'nature' => $accountType['nature'],
                'remark' => $accountType['remark'],
                'company_id' => Company::withoutGlobalScopes()->value('id'),
                'created_by' => User::withoutGlobalScopes()->where('email', 'admin@nextbook.af')->first()->id,
            ]);
        }
    }
}
