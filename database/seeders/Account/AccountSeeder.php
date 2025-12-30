<?php

namespace Database\Seeders\Account;

use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the main branch
        $mainBranch = Branch::where('is_main', true)->first();

        $accounts = Account::defaultAccounts();

        foreach ($accounts as $account) {
            Account::create([
                'id' => (string) new Ulid(),
                'name' => $account['name'],
                'number' => $account['number'],
                'account_type_id' => $account['account_type_id'],
                'slug' => $account['slug'],
                'branch_id' => $mainBranch?->id,
                'remark' => $account['remark'],
                'is_main' => $account['is_main'],
                'created_by' => User::first()->id,
            ]);
        }
    }
}
