<?php

namespace Database\Seeders\Account;

use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Branch;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountTypes = \App\Models\Account\AccountType::pluck('id')->toArray();

        // Get the main branch
        $mainBranch = Branch::where('is_main', true)->first();
        if (!$mainBranch) {
            throw new \Exception('Main branch not found. Please run BranchSeeder first.');
        }

        $accounts = [
            [
                'name' => 'Cash',
                'number' => 'Ac-101',
                'account_type_id' => $accountTypes[9],
                'branch_id' => $mainBranch->id,
                'remark' => 'Cash-In-Hand Account Type',
            ],
            [
                'name' => 'Sarafi',
                'number' => 'Ac-103',
                'account_type_id' => $accountTypes[10],
                'branch_id' => $mainBranch->id,
                'remark' => 'Sarafi Account Type',
            ],
            [
                'name' => 'Account Payable',
                'number' => 'Ac-201',
                'account_type_id' => AccountType::where('name', 'Account Payable')->first()->id,
                'branch_id' => $mainBranch->id,
                'remark' => 'Account Payable Account',
            ],
            [
                'name' => 'Account Receivable',
                'number' => 'Ac-301',
                'account_type_id' => AccountType::where('name', 'Account Receivable')->first()->id,
                'branch_id' => $mainBranch->id,
                'remark' => 'Account Receivable Account',
            ],
            [
                'name' => 'Store',
                'number' => 'Ac-401',
                'account_type_id' => $accountTypes[5],
                'branch_id' => $mainBranch->id,
                'remark' => 'Store Account Type',
            ],
            [
                'name' => 'Equity',
                'number' => 'Ac-501',
                'account_type_id' => AccountType::where('name', 'Equity')->first()->id,
                'branch_id' => $mainBranch->id,
                'remark' => 'Equity Account Type',
            ],
            [
                'name' => 'Gains/Losses',
                'number' => 'Ac-601',
                'account_type_id' => AccountType::where('name', 'Gains/Losses')->first()->id,
                'branch_id' => $mainBranch->id,
                'remark' => 'Gains/Losses Account Type',
            ],
            [
                'name' => 'Cost of Goods Sold',
                'number' => 'Ac-901',
                'account_type_id' => $accountTypes[0],
                'branch_id' => $mainBranch->id,
                'remark' => 'Cost of Goods Sold Account Type',
            ],
        ];

        foreach ($accounts as $account) {
            Account::factory()->create($account);
        }
    }
}
