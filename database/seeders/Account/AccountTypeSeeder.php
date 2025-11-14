<?php

namespace Database\Seeders\Account;

use App\Models\Account\AccountType;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;

class AccountTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $accountTypes = [
            [
                'name' => "Account Payable",
                'slug' => 'account-payable',
                'remark' => 'Account Payable Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Account Receivable",
                'slug' => 'account-receivable',
                'remark' => 'Account Receivable Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Cost of Goods Sold",
                'slug' => 'cost-of-goods-sold',
                'remark' => 'Cost of Goods Sold Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Gains/Losses",
                'slug' => 'gains-losses',
                'remark' => 'Gains/Losses Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Equity",
                'slug' => 'equity',
                'remark' => 'Equity Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Store",
                'slug' => 'store',
                'remark' => 'Store Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Bank Account",
                'slug' => 'bank-account',
                'remark' => 'Bank Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Cash-In-Hand",
                'slug' => 'cash-in-hand',
                'remark' => 'Cash-In-Hand Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Sarafi",
                'slug' => 'sarafi',
                'remark' => 'Sarafi Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Expanses(Direct)(Mfg/Trdg.Expenses)",
                'slug' => 'expanses-direct-mfg-trdg-expenses',
                'remark' => 'Expanses(Direct)(Mfg/Trdg.Expenses) Account Type',
                'is_main' => true,
            ],
            [
                'name' => "NON-CURRENT ASSETS",
                'slug' => 'non-current-assets',
                'remark' => 'NON-CURRENT ASSETS Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Expanses(Indirect)(Admin.Expenses)",
                'slug' => 'expanses-indirect-admin-expenses',
                'remark' => 'Expanses(Indirect)(Admin.Expenses) Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Current Asset",
                'slug' => 'current-asset',
                'remark' => 'Current Asset Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Fixed Asset",
                'slug' => 'fixed-asset',
                'remark' => 'Fixed Asset Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Loan & advance (Asset)",
                'slug' => 'loan-advance-asset',
                'remark' => 'Loan & advance (Asset) Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Loans(Liability)",
                'slug' => 'loans-liability',
                'remark' => 'Loans(Liability) Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Current Investments",
                'slug' => 'current-investments',
                'remark' => 'Current Investments Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Current Liability",
                'slug' => 'current-liability',
                'remark' => 'Current Liability Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Employee Benefit Expanse",
                'slug' => 'employee-benefit-expanse',
                'remark' => 'Employee Benefit Expanse Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Expenditure Account",
                'slug' => 'expenditure-account',
                'remark' => 'Expenditure Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Capital Work-In Progress",
                'slug' => 'capital-work-in-progress',
                'remark' => 'Capital Work-In Progress Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Investment",
                'slug' => 'investment',
                'remark' => 'Investment Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Sales Tax",
                'slug' => 'sales-tax',
                'remark' => 'Sales Tax Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Security and Deposit(Asset)",
                'slug' => 'security-and-deposit-asset',
                'remark' => 'Security and Deposit(Asset) Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Share Capital",
                'slug' => 'share-capital',
                'remark' => 'Share Capital Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Sundry Creditor(Manufacturers)",
                'slug' => 'sundry-creditor-manufacturers',
                'remark' => 'Sundry Creditor(Manufacturers) Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Sundry Creditor(Suppliers)",
                'slug' => 'sundry-creditor-suppliers',
                'remark' => 'Sundry Creditor(Suppliers) Account Type',
                'is_main' => true,
            ],

            [
                'name' => "BAD DEBIT, WASTAGE",
                'slug' => 'bad-debit-wastage',
                'remark' => 'BAD DEBIT, WASTAGE Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Store T/F",
                'slug' => 'store-tf',
                'remark' => 'Store T/F Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Self Breakage/Expired/Wastage",
                'slug' => 'self-breakage-expired-wastage',
                'remark' => 'Self Breakage/Expired/Wastage Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Short & Excess",
                'slug' => 'short-excess',
                'remark' => 'Short & Excess Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Indirect Income",
                'slug' => 'indirect-income',
                'remark' => 'Indirect Income Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Stock-In-Hand ",
                'slug' => 'stock-in-hand',
                'remark' => 'Stock-In-Hand Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Depreciation A/C",
                'slug' => 'depreciation-ac',
                'remark' => 'Depreciation A/C Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Import",
                'slug' => 'import',
                'remark' => 'Import Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Negative value of bill",
                'slug' => 'negative-value-of-bill',
                'remark' => 'Negative value of bill Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Office Expenses",
                'slug' => 'office-expenses',
                'remark' => 'Office Expenses Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Profit & Los A/C",
                'slug' => 'profit-los-ac',
                'remark' => 'Profit & Los A/C Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Round Off",
                'slug' => 'round-off',
                'remark' => 'Round Off Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Capital Account",
                'slug' => 'capital-account',
                'remark' => 'Capital Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Income",
                'slug' => 'income',
                'remark' => 'Income Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Income Indirect ",
                'slug' => 'income-indirect',
                'remark' => 'Income Indirect Account Type',
                'is_main' => true,
            ],
            [
                'name' => "Sales Revenue",
                'slug' => 'sales-revenue',
                'remark' => 'Sales Revenue Account Type',
                'is_main' => true,
            ],


        ];

        foreach ($accountTypes as $accountType) {
            AccountType::create([
                'id' => Str::uuid(),
                'name' => $accountType['name'],
                'is_main' => $accountType['is_main'],
                'slug' => $accountType['slug'],
                'remark' => $accountType['remark'],
                'created_by' => User::first()->id,
            ]);
        }
    }
}
