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
        if (!$mainBranch) {
            throw new \Exception('Main branch not found. Please run BranchSeeder first.');
        }

        $accounts = [
    // ================= ASSETS =================
        [
            'name' => 'Cash',
            'number' => '1001',
            'account_type_id' => AccountType::where('slug', 'cash-in-hand')->first()->id,
            'slug' => 'cash',
            'branch_id' => $mainBranch->id,
            'remark' => 'Physical cash available',
            'is_main' => true,
        ],
        [
            'name' => 'Bank AFN',
            'number' => '1002',
            'account_type_id' => AccountType::where('slug', 'bank-account')->first()->id,
            'slug' => 'bank-afn',
            'branch_id' => $mainBranch->id,
            'remark' => 'AFN currency bank account',
            'is_main' => true,
        ],
        [
            'name' => 'Bank USD',
            'number' => '1003',
            'account_type_id' => AccountType::where('slug', 'bank-account')->first()->id,
            'slug' => 'bank-usd',
            'branch_id' => $mainBranch->id,
            'remark' => 'USD currency bank account',
            'is_main' => true,
        ],
        [
            'name' => 'Accounts Receivable',
            'number' => '1101',
            'account_type_id' => AccountType::where('slug', 'account-receivable')->first()->id,
            'slug' => 'accounts-receivable',
            'branch_id' => $mainBranch->id,
            'remark' => 'Money owed by customers',
            'is_main' => true,
        ],
        [
            'name' => 'Inventory',
            'number' => '1201',
            'account_type_id' => AccountType::where('slug', 'inventory')->first()->id,
            'slug' => 'inventory',
            'branch_id' => $mainBranch->id,
            'remark' => 'Goods held for sale',
            'is_main' => true,
        ],
        [
            'name' => 'Prepaid Expenses',
            'number' => '1301',
            'account_type_id' => AccountType::where('slug', 'prepaid-expenses')->first()->id,
            'slug' => 'prepaid-expenses',
            'branch_id' => $mainBranch->id,
            'remark' => 'Expenses paid in advance',
            'is_main' => true,
        ],
        [
            'name' => 'Loans Receivable',
            'number' => '1401',
            'account_type_id' => AccountType::where('slug', 'loan-advance-asset')->first()->id,
            'slug' => 'loans-receivable',
            'branch_id' => $mainBranch->id,
            'remark' => 'Loans given to others',
            'is_main' => true,
        ],
        [
            'name' => 'Security Deposits',
            'number' => '1501',
            'account_type_id' => AccountType::where('slug', 'security-and-deposit-asset')->first()->id,
            'slug' => 'security-deposits',
            'branch_id' => $mainBranch->id,
            'remark' => 'Security deposits paid',
            'is_main' => true,
        ],
        // Contra Asset
        [
            'name' => 'Allowance for Doubtful Accounts',
            'number' => '1901',
            'account_type_id' => AccountType::where('slug', 'allowance-doubtful-accounts')->first()->id,
            'slug' => 'allowance-doubtful-accounts',
            'branch_id' => $mainBranch->id,
            'remark' => 'Estimated uncollectible receivables',
            'is_main' => true,
        ],

        // ================= LIABILITIES =================
        [
            'name' => 'Accounts Payable',
            'number' => '2001',
            'account_type_id' => AccountType::where('slug', 'account-payable')->first()->id,
            'slug' => 'accounts-payable',
            'branch_id' => $mainBranch->id,
            'remark' => 'Money owed to suppliers',
            'is_main' => true,
        ],
        [
            'name' => 'Sales Tax Payable',
            'number' => '2101',
            'account_type_id' => AccountType::where('slug', 'sales-tax')->first()->id,
            'slug' => 'sales-tax-payable',
            'branch_id' => $mainBranch->id,
            'remark' => 'Sales tax collected payable',
            'is_main' => true,
        ],
        [
            'name' => 'Accrued Expenses',
            'number' => '2201',
            'account_type_id' => AccountType::where('slug', 'accrued-expenses')->first()->id,
            'slug' => 'accrued-expenses',
            'branch_id' => $mainBranch->id,
            'remark' => 'Expenses incurred but not paid',
            'is_main' => true,
        ],
        [
            'name' => 'Deferred Revenue',
            'number' => '2301',
            'account_type_id' => AccountType::where('slug', 'deferred-revenue')->first()->id,
            'slug' => 'deferred-revenue',
            'branch_id' => $mainBranch->id,
            'remark' => 'Advance payments from customers',
            'is_main' => true,
        ],
        [
            'name' => 'Bank Loan',
            'number' => '2401',
            'account_type_id' => AccountType::where('slug', 'loans-liability')->first()->id,
            'slug' => 'bank-loan',
            'branch_id' => $mainBranch->id,
            'remark' => 'Bank loans payable',
            'is_main' => true,
        ],

        // ================= EQUITY =================
        [
            'name' => "Owner's Capital",
            'number' => '3001',
            'account_type_id' => AccountType::where('slug', 'capital-account')->first()->id,
            'slug' => 'owners-capital',
            'branch_id' => $mainBranch->id,
            'remark' => 'Owner capital contributions',
            'is_main' => true,
        ],
        [
            'name' => "Owner's Drawings",
            'number' => '3002',
            'account_type_id' => AccountType::where('slug', 'drawings')->first()->id,
            'slug' => 'owners-drawings',
            'branch_id' => $mainBranch->id,
            'remark' => 'Owner personal withdrawals',
            'is_main' => true,
        ],
        [
            'name' => 'Retained Earnings',
            'number' => '3003',
            'account_type_id' => AccountType::where('slug', 'retained-earnings')->first()->id,
            'slug' => 'retained-earnings',
            'branch_id' => $mainBranch->id,
            'remark' => 'Accumulated profits/losses',
            'is_main' => true,
        ],
        [
            'name' => 'Opening Balance Equity',
            'number' => '3004',
            'account_type_id' => AccountType::where('slug', 'opening-balance-equity')->first()->id,
            'slug' => 'opening-balance-equity',
            'branch_id' => $mainBranch->id,
            'remark' => 'Initial business equity',
            'is_main' => true,
        ],
        [
            'name' => 'Current Year Earnings',
            'number' => '3005',
            'account_type_id' => AccountType::where('slug', 'profit-loss-ac')->first()->id,
            'slug' => 'current-year-earnings',
            'branch_id' => $mainBranch->id,
            'remark' => 'Current year profit/loss',
            'is_main' => true,
        ],

        // ================= INCOME =================
        [
            'name' => 'Sales Revenue',
            'number' => '4001',
            'account_type_id' => AccountType::where('slug', 'sales-revenue')->first()->id,
            'slug' => 'sales-revenue',
            'branch_id' => $mainBranch->id,
            'remark' => 'Revenue from sales',
            'is_main' => true,
        ],
        [
            'name' => 'Service Income',
            'number' => '4101',
            'account_type_id' => AccountType::where('slug', 'income')->first()->id,
            'slug' => 'service-income',
            'branch_id' => $mainBranch->id,
            'remark' => 'Income from services',
            'is_main' => true,
        ],
        [
            'name' => 'Interest Income',
            'number' => '4201',
            'account_type_id' => AccountType::where('slug', 'indirect-income')->first()->id,
            'slug' => 'interest-income',
            'branch_id' => $mainBranch->id,
            'remark' => 'Interest earned',
            'is_main' => true,
        ],
        // Contra Revenue
        [
            'name' => 'Sales Returns & Allowances',
            'number' => '4901',
            'account_type_id' => AccountType::where('slug', 'sales-returns-allowances')->first()->id,
            'slug' => 'sales-returns-allowances',
            'branch_id' => $mainBranch->id,
            'remark' => 'Returns and discounts given',
            'is_main' => true,
        ],
        [
            'name' => 'Discounts Given',
            'number' => '4902',
            'account_type_id' => AccountType::where('slug', 'discounts-given')->first()->id,
            'slug' => 'discounts-given',
            'branch_id' => $mainBranch->id,
            'remark' => 'Discounts offered to customers',
            'is_main' => true,
        ],

        // ================= EXPENSES =================
        [
            'name' => 'Cost of Goods Sold',
            'number' => '5001',
            'account_type_id' => AccountType::where('slug', 'cost-of-goods-sold')->first()->id,
            'slug' => 'cost-of-goods-sold',
            'branch_id' => $mainBranch->id,
            'remark' => 'Direct cost of products sold',
            'is_main' => true,
        ],
        [
            'name' => 'Office Expenses',
            'number' => '5101',
            'account_type_id' => AccountType::where('slug', 'office-expenses')->first()->id,
            'slug' => 'office-expenses',
            'branch_id' => $mainBranch->id,
            'remark' => 'General office expenses',
            'is_main' => true,
        ],
        [
            'name' => 'Salaries & Wages',
            'number' => '5201',
            'account_type_id' => AccountType::where('slug', 'employee-benefit-expense')->first()->id,
            'slug' => 'salaries-wages',
            'branch_id' => $mainBranch->id,
            'remark' => 'Employee salaries and wages',
            'is_main' => true,
        ],
        [
            'name' => 'Rent Expense',
            'number' => '5301',
            'account_type_id' => AccountType::where('slug', 'expenses-indirect')->first()->id,
            'slug' => 'rent-expense',
            'branch_id' => $mainBranch->id,
            'remark' => 'Rental payments',
            'is_main' => true,
        ],
        [
            'name' => 'Utilities Expense',
            'number' => '5401',
            'account_type_id' => AccountType::where('slug', 'expenses-indirect')->first()->id,
            'slug' => 'utilities-expense',
            'branch_id' => $mainBranch->id,
            'remark' => 'Electricity, water, internet',
            'is_main' => true,
        ],
        [
            'name' => 'Marketing Expense',
            'number' => '5501',
            'account_type_id' => AccountType::where('slug', 'expenses-indirect')->first()->id,
            'slug' => 'marketing-expense',
            'branch_id' => $mainBranch->id,
            'remark' => 'Advertising and promotion',
            'is_main' => true,
        ],
        [
            'name' => 'Freight & Shipping',
            'number' => '5601',
            'account_type_id' => AccountType::where('slug', 'freight-shipping')->first()->id,
            'slug' => 'freight-shipping',
            'branch_id' => $mainBranch->id,
            'remark' => 'Transportation costs',
            'is_main' => true,
        ],
        [
            'name' => 'Sarafi',
            'number' => '5701',
            'account_type_id' => AccountType::where('slug', 'sarafi')->first()->id,
            'slug' => 'sarafi',
            'branch_id' => $mainBranch->id,
            'remark' => 'Miscellaneous expenses',
            'is_main' => true,
        ],
        [
            'name' => 'Depreciation Expense',
            'number' => '5801',
            'account_type_id' => AccountType::where('slug', 'depreciation-ac')->first()->id,
            'slug' => 'depreciation-expense',
            'branch_id' => $mainBranch->id,
            'remark' => 'Depreciation of fixed assets',
            'is_main' => true,
        ],
        [
            'name' => 'Bad Debts Expense',
            'number' => '5901',
            'account_type_id' => AccountType::where('slug', 'bad-debts-wastage')->first()->id,
            'slug' => 'bad-debts-expense',
            'branch_id' => $mainBranch->id,
            'remark' => 'Uncollectible accounts',
            'is_main' => true,
        ],
        // Contra Expense
        [
            'name' => 'Discounts Received',
            'number' => '5991',
            'account_type_id' => AccountType::where('slug', 'discounts-received')->first()->id,
            'slug' => 'discounts-received',
            'branch_id' => $mainBranch->id,
            'remark' => 'Discounts from suppliers',
            'is_main' => true,
        ],

        // ================= OTHER =================
        [
            'name' => 'Gains/Losses on Sale',
            'number' => '6001',
            'account_type_id' => AccountType::where('slug', 'gains-losses')->first()->id,
            'slug' => 'gains-losses-on-sale',
            'branch_id' => $mainBranch->id,
            'remark' => 'Gains or losses on asset sales',
            'is_main' => true,
        ],
        [
            'name' => 'Inventory Adjustments',
            'number' => '6101',
            'account_type_id' => AccountType::where('slug', 'short-excess')->first()->id,
            'slug' => 'inventory-adjustments',
            'branch_id' => $mainBranch->id,
            'remark' => 'Inventory discrepancies',
            'is_main' => true,
        ],
        [
            'name' => 'Round Off',
            'number' => '6201',
            'account_type_id' => AccountType::where('slug', 'round-off')->first()->id,
            'slug' => 'round-off',
            'branch_id' => $mainBranch->id,
            'remark' => 'Rounding differences',
            'is_main' => true,
        ],
    ];

        foreach ($accounts as $account) {
            Account::create([
                'id' => (string) new Ulid(),
                'name' => $account['name'],
                'number' => $account['number'],
                'account_type_id' => $account['account_type_id'],
                'slug' => $account['slug'],
                'branch_id' => $account['branch_id'],
                'remark' => $account['remark'],
                'is_main' => $account['is_main'],
                'created_by' => User::first()->id,
            ]);
        }
    }
}
