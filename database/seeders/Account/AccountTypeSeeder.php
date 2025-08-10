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
                'remark' => 'Account Payable Account Type',
            ],
            [
                'name' => "Account Receivable",
                'remark' => 'Account Receivable Account Type',
            ],
            [
                'name' => "Cost of Goods Sold",
                'remark' => 'Cost of Goods Sold Account Type',
            ],
            [
                'name' => "Gains/Losses",
                'remark' => 'Gains/Losses Account Type',
            ],
            [
                'name' => "Equity",
                'remark' => 'Equity Account Type',
            ],
            [
                'name' => "Store",
                'remark' => 'Store Account Type',
            ],
            [
                'name' => "Bank Account",
                'remark' => 'Bank Account Type',
            ],
            [
                'name' => "Cash-In-Hand",
                'remark' => 'Cash-In-Hand Account Type',
            ],
            [
                'name' => "Sarafi",
                'remark' => 'Sarafi Account Type',
            ],
            [
                'name' => "Expanses(Direct)(Mfg/Trdg.Expenses)",
                'remark' => 'Expanses(Direct)(Mfg/Trdg.Expenses) Account Type',
            ],
            [
                'name' => "NON-CURRENT ASSETS",
                'remark' => 'NON-CURRENT ASSETS Account Type',
            ],
            [
                'name' => "Expanses(Indirect)(Admin.Expenses)",
                'remark' => 'Expanses(Indirect)(Admin.Expenses) Account Type',
            ],
            [
                'name' => "Current Asset",
                'remark' => 'Current Asset Account Type',
            ],
            [
                'name' => "Fixed Asset",
                'remark' => 'Fixed Asset Account Type',
            ],
            [
                'name' => "Loan & advance (Asset)",
                'remark' => 'Loan & advance (Asset) Account Type',
            ],
            [
                'name' => "Loans(Liability)",
                'remark' => 'Loans(Liability) Account Type',
            ],
            [
                'name' => "Current Investments",
                'remark' => 'Current Investments Account Type',
            ],
            [
                'name' => "Current Liability",
                'remark' => 'Current Liability Account Type',
            ],
            [
                'name' => "Employee Benefit Expanse",
                'remark' => 'Employee Benefit Expanse Account Type',
            ],
            [
                'name' => "Expenditure Account",
                'remark' => 'Expenditure Account Type',
            ],
            [
                'name' => "Capital Work-In Progress",
                'remark' => 'Capital Work-In Progress Account Type',
            ],
            [
                'name' => "Investment",
                'remark' => 'Investment Account Type',
            ],
            [
                'name' => "Sales Tax",
                'remark' => 'Sales Tax Account Type',
            ],
            [
                'name' => "Security and Deposit(Asset)",
                'remark' => 'Security and Deposit(Asset) Account Type',
            ],
            [
                'name' => "Share Capital",
                'remark' => 'Share Capital Account Type',
            ],
            [
                'name' => "Sundry Creditor(Manufacturers)",
                'remark' => 'Sundry Creditor(Manufacturers) Account Type',
            ],
            [
                'name' => "Sundry Creditor(Suppliers)",
                'remark' => 'Sundry Creditor(Suppliers) Account Type',
            ],

            [
                'name' => "BAD DEBIT, WASTAGE",
                'remark' => 'BAD DEBIT, WASTAGE Account Type',
            ],
            [
                'name' => "Store T/F",
                'remark' => 'Store T/F Account Type',
            ],
            [
                'name' => "Self Breakage/Expired/Wastage",
                'remark' => 'Self Breakage/Expired/Wastage Account Type',
            ],
            [
                'name' => "Short & Excess",
                'remark' => 'Short & Excess Account Type',
            ],
            [
                'name' => "Indirect Income",
                'remark' => 'Indirect Income Account Type',
            ],
            [
                'name' => "Stock-In-Hand ",
                'remark' => 'Stock-In-Hand Account Type',
            ],
            [
                'name' => "Depreciation A/C",
                'remark' => 'Depreciation A/C Account Type',
            ],
            [
                'name' => "Import",
                'remark' => 'Import Account Type',
            ],
            [
                'name' => "Negative value of bill",
                'remark' => 'Negative value of bill Account Type',
            ],
            [
                'name' => "Office Expenses",
                'remark' => 'Office Expenses Account Type',
            ],
            [
                'name' => "Profit & Los A/C",
                'remark' => 'Profit & Los A/C Account Type',
            ],
            [
                'name' => "Round Off",
                'remark' => 'Round Off Account Type',
            ],
            [
                'name' => "Capital Account",
                'remark' => 'Capital Account Type',
            ],
            [
                'name' => "Income Direct",
                'remark' => 'Income Direct Account Type',
            ],
            [
                'name' => "Income Indirect ",
                'remark' => 'Income Indirect Account Type',
            ],


        ];

        foreach ($accountTypes as $accountType) {
            AccountType::create([
                'id' => Str::uuid(),
                'name' => $accountType['name'],
                'remark' => $accountType['remark'],
                'created_by' => User::first()->id,
            ]);
        }
    }
}
