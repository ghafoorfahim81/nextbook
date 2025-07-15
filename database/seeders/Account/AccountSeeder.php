<?php

namespace Database\Seeders\Account;

use App\Models\Account\Account;
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
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Cash-In-Hand Account Type',
            ],
            [
                'name' => 'Azizi Bank',
                'number' => 'Ac-102',
                'account_type_id' => $accountTypes[8],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Bank Account Type',
            ],
            [
                'name' => 'Sarafi',
                'number' => 'Ac-103',
                'account_type_id' => $accountTypes[10],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Sarafi Account Type',
            ],
            [
                'name' => 'Sundry Debtors',
                'number' => 'Ac-201',
                'account_type_id' => $accountTypes[6],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Sundry Debtors Account Type',
            ],
            [
                'name' => 'Sundry Creditors',
                'number' => 'Ac-301',
                'account_type_id' => $accountTypes[7],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Sundry Creditors Account Type',
            ],
            [
                'name' => 'Store',
                'number' => 'Ac-401',
                'account_type_id' => $accountTypes[5],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Store Account Type',
            ],
            [
                'name' => 'Equity',
                'number' => 'Ac-501',
                'account_type_id' => $accountTypes[4],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Equity Account Type',
            ],
            [
                'name' => 'Gains/Losses',
                'number' => 'Ac-601',
                'account_type_id' => $accountTypes[3],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Gains/Losses Account Type',
            ],
            [
                'name' => 'Accounts Payable',
                'number' => 'Ac-701',
                'account_type_id' => $accountTypes[2],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Accounts Payable Account Type',
            ],
            [
                'name' => 'Accounts Receivable',
                'number' => 'Ac-801',
                'account_type_id' => $accountTypes[1],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Accounts Receivable Account Type',
            ],
            [
                'name' => 'Cost of Goods Sold',
                'number' => 'Ac-901',
                'account_type_id' => $accountTypes[0],
                'parent_id' => null,
                'branch_id' => $mainBranch->id,
                'remark' => 'Cost of Goods Sold Account Type',
            ],
        ];

        foreach ($accounts as $account) {
            Account::factory()->create($account);
        }
    }
}
