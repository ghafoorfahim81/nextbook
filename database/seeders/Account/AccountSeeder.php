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
        // Get the main branch
        $mainBranch = Branch::where('is_main', true)->first();
        if (!$mainBranch) {
            throw new \Exception('Main branch not found. Please run BranchSeeder first.');
        }

        $accounts = [
            [
                'name' => 'Cash',
                'number' => 'Cas-001',
                'account_type_id' => AccountType::where('slug', 'cash-in-hand')->first()->id,
                'slug' => 'cash-in-hand',
                'branch_id' => $mainBranch->id,
                'remark' => 'Cash-In-Hand Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Sarafi',
                'number' => 'Sar-002',
                'account_type_id' => AccountType::where('slug', 'sarafi')->first()->id,
                'slug' => 'sarafi',
                'branch_id' => $mainBranch->id,
                'remark' => 'Sarafi Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Account Payable',
                'number' => 'AcP-003',
                'account_type_id' => AccountType::where('slug', 'account-payable')->first()->id,
                'slug' => 'account-payable',
                'branch_id' => $mainBranch->id,
                'remark' => 'Account Payable Account',
                'is_main' => true,
            ],
            [
                'name' => 'Account Receivable',
                'number' => 'AcR-004',
                'account_type_id' => AccountType::where('slug', 'account-receivable')->first()->id,
                'slug' => 'account-receivable',
                'branch_id' => $mainBranch->id,
                'remark' => 'Account Receivable Account',
                'is_main' => true,
            ],
            [
                'name' => 'opening balance equity',
                'number' => 'OBE-005',
                'account_type_id' => AccountType::where('slug', 'equity')->first()->id,
                'slug' => 'opening-balance-equity',
                'branch_id' => $mainBranch->id,
                'remark' => 'opening balance equity Account',
                'is_main' => true,
            ],
            [
                'name' => 'Income',
                'number' => 'Inc-006',
                'account_type_id' => AccountType::where('slug', 'income')->first()->id,
                'slug' => 'income',
                'branch_id' => $mainBranch->id,
                'remark' => 'Income Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Cost of Goods Sold',
                'number' => 'Cogs-007',
                'account_type_id' => AccountType::where('slug', 'cost-of-goods-sold')->first()->id,
                'slug' => 'cost-of-goods-sold',
                'branch_id' => $mainBranch->id,
                'remark' => 'Cost of Goods Sold Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Bank AFN',
                'number' => 'BnA-008',
                'account_type_id' => AccountType::where('slug', 'bank-account')->first()->id,
                'slug' => 'bank-afn',
                'branch_id' => $mainBranch->id,
                'remark' => 'Bank Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Bank USD',
                'number' => 'BnU-009',
                'account_type_id' => AccountType::where('slug', 'bank-account')->first()->id,
                'slug' => 'bank-usd',
                'branch_id' => $mainBranch->id,
                'remark' => 'Bank Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Inventory asset',
                'number' => 'InA-010',
                'account_type_id' => AccountType::where('slug', 'non-current-assets')->first()->id,
                'slug' => 'inventory-asset',
                'branch_id' => $mainBranch->id,
                'remark' => 'Inventory asset Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Store',
                'number' => 'Sto-011',
                'account_type_id' => AccountType::where('slug', 'store')->first()->id,
                'slug' => 'store',
                'branch_id' => $mainBranch->id,
                'remark' => 'Store Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Equity',
                'number' => 'Equ-012',
                'account_type_id' => AccountType::where('slug', 'equity')->first()->id,
                'slug' => 'equity',
                'branch_id' => $mainBranch->id,
                'remark' => 'Equity Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Gains/Losses',
                'number' => 'GaL-013',
                'account_type_id' => AccountType::where('slug', 'gains-losses')->first()->id,
                'slug' => 'gains-losses',
                'branch_id' => $mainBranch->id,
                'remark' => 'Gains/Losses Account Type',
                'is_main' => true,
            ],
            [
                'name' => 'Sales Revenue',
                'number' => 'Sr-014',
                'account_type_id' => AccountType::where('slug', 'sales-revenue')->first()->id,
                'slug' => 'sales-revenue',
                'branch_id' => $mainBranch->id,
                'remark' => 'Sales Revenue Account Type',
                'is_main' => true,
            ], 
            [
                'name' => 'Owner\'s Capital',
                'number' => 'OC-015',
                'account_type_id' => AccountType::where('slug', 'equity')->first()->id,
                'slug' => 'owners-capital',
                'branch_id' => $mainBranch->id,
                'remark' => 'Owner capital contributions',
                'is_main' => true,
            ],
            [
                'name' => 'Owner\'s Drawing',
                'number' => 'OD-016',
                'account_type_id' => AccountType::where('slug', 'equity')->first()->id,
                'slug' => 'owners-drawing',
                'branch_id' => $mainBranch->id,
                'remark' => 'Owner personal withdrawals',
                'is_main' => true,
            ],
            [
                'name' => 'Retained Earnings',
                'number' => 'RE-017',
                'account_type_id' => AccountType::where('slug', 'equity')->first()->id,
                'slug' => 'retained-earnings',
                'branch_id' => $mainBranch->id,
                'remark' => 'Accumulated profits/losses',
                'is_main' => true,
            ]

        ];

        foreach ($accounts as $account) {
            Account::factory()->create($account);
        }
    }
}
