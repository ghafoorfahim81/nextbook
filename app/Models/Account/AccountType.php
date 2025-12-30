<?php

namespace App\Models\Account;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasBranch;
use App\Traits\BranchSpecific;
class AccountType extends Model
{
    use HasFactory, HasSearch, HasSorting, BranchSpecific, HasBranch, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $table = 'account_types';
    protected $keyType = 'string';
    public $incrementing = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new \Symfony\Component\Uid\Ulid();
        });
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'remark',
        'slug',
        'is_main',
        'created_by',
        'updated_by',
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'slug' => 'string',
        'is_main' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'remark',
        ];
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'accounts' => [
                'model' => 'accounts',
                'message' => 'This account type is used in accounts'
            ]
        ];
    }

    public static function defaultAccountTypes(): array
    {
        return [
            // ================= ASSETS =================
            [
                'name' => "Cash-In-Hand",
                'slug' => 'cash',
                'remark' => 'Physical cash available',
                'is_main' => true,
            ],
            [
                'name' => "Bank Account",
                'slug' => 'bank-account',
                'remark' => 'Bank accounts and deposits',
                'is_main' => true,
            ],
            [
                'name' => "Account Receivable",
                'slug' => 'account-receivable',
                'remark' => 'Money owed by customers',
                'is_main' => true,
            ],
            [
                'name' => "Inventory",
                'slug' => 'inventory',
                'remark' => 'Goods held for sale',
                'is_main' => true,
            ],
            [
                'name' => "Prepaid Expenses",
                'slug' => 'prepaid-expenses',
                'remark' => 'Expenses paid in advance',
                'is_main' => true,
            ],
            [
                'name' => "Current Investments",
                'slug' => 'current-investments',
                'remark' => 'Short-term investments',
                'is_main' => true,
            ],
            [
                'name' => "Loan & Advances (Asset)",
                'slug' => 'loan-advance-asset',
                'remark' => 'Loans given to others',
                'is_main' => true,
            ],
            [
                'name' => "Security and Deposit (Asset)",
                'slug' => 'security-and-deposit-asset',
                'remark' => 'Security deposits paid',
                'is_main' => true,
            ],
            [
                'name' => "Current Asset",
                'slug' => 'current-asset',
                'remark' => 'Other current assets',
                'is_main' => true,
            ],
            // Fixed Assets
            [
                'name' => "Fixed Asset",
                'slug' => 'fixed-asset',
                'remark' => 'Long-term tangible assets',
                'is_main' => true,
            ],
            [
                'name' => "Non-Current Assets",
                'slug' => 'non-current-assets',
                'remark' => 'Long-term assets',
                'is_main' => true,
            ],
            [
                'name' => "Capital Work-In Progress",
                'slug' => 'capital-work-in-progress',
                'remark' => 'Assets under construction',
                'is_main' => true,
            ],
            [
                'name' => "Investment",
                'slug' => 'investment',
                'remark' => 'Long-term investments',
                'is_main' => true,
            ],
            // Contra Assets
            [
                'name' => "Accumulated Depreciation",
                'slug' => 'accumulated-depreciation',
                'remark' => 'Total depreciation on fixed assets',
                'is_main' => true,
            ],
            [
                'name' => "Allowance for Doubtful Accounts",
                'slug' => 'allowance-doubtful-accounts',
                'remark' => 'Estimated uncollectible receivables',
                'is_main' => true,
            ],

            // ================= LIABILITIES =================
            [
                'name' => "Account Payable",
                'slug' => 'account-payable',
                'remark' => 'Money owed to suppliers',
                'is_main' => true,
            ],
            [
                'name' => "Loans (Liability)",
                'slug' => 'loans-liability',
                'remark' => 'Borrowed money to be repaid',
                'is_main' => true,
            ],
            [
                'name' => "Current Liability",
                'slug' => 'current-liability',
                'remark' => 'Short-term obligations',
                'is_main' => true,
            ],
            [
                'name' => "Accrued Expenses",
                'slug' => 'accrued-expenses',
                'remark' => 'Expenses incurred but not paid',
                'is_main' => true,
            ],
            [
                'name' => "Deferred Revenue",
                'slug' => 'deferred-revenue',
                'remark' => 'Advance payments from customers',
                'is_main' => true,
            ],
            [
                'name' => "Sales Tax",
                'slug' => 'sales-tax',
                'remark' => 'Sales tax collected payable',
                'is_main' => true,
            ],
            [
                'name' => "Sundry Creditor (Manufacturers)",
                'slug' => 'sundry-creditor-manufacturers',
                'remark' => 'Owed to manufacturers',
                'is_main' => true,
            ],
            [
                'name' => "Sundry Creditor (Suppliers)",
                'slug' => 'sundry-creditor-suppliers',
                'remark' => 'Owed to suppliers',
                'is_main' => true,
            ],

            // ================= EQUITY =================
            [
                'name' => "Equity",
                'slug' => 'equity',
                'remark' => 'General equity accounts',
                'is_main' => true,
            ],
            [
                'name' => "Capital Account",
                'slug' => 'capital-account',
                'remark' => 'Owner capital contributions',
                'is_main' => true,
            ],
            [
                'name' => "Share Capital",
                'slug' => 'share-capital',
                'remark' => 'Capital from shareholders',
                'is_main' => true,
            ],
            [
                'name' => "Retained Earnings",
                'slug' => 'retained-earnings',
                'remark' => 'Accumulated profits',
                'is_main' => true,
            ],
            [
                'name' => "Drawings",
                'slug' => 'drawings',
                'remark' => 'Owner personal withdrawals',
                'is_main' => true,
            ],
            [
                'name' => "Opening Balance Equity",
                'slug' => 'opening-balance-equity',
                'remark' => 'Initial business equity',
                'is_main' => true,
            ],
            [
                'name' => "Profit & Loss A/C",
                'slug' => 'profit-loss-ac',
                'remark' => 'Temporary profit/loss account',
                'is_main' => true,
            ],

            // ================= INCOME =================
            [
                'name' => "Sales Revenue",
                'slug' => 'sales-revenue',
                'remark' => 'Revenue from sales',
                'is_main' => true,
            ],
            [
                'name' => "Income",
                'slug' => 'income',
                'remark' => 'General income accounts',
                'is_main' => true,
            ],
            [
                'name' => "Indirect Income",
                'slug' => 'indirect-income',
                'remark' => 'Non-operating income',
                'is_main' => true,
            ],
            [
                'name' => "Import",
                'slug' => 'import',
                'remark' => 'Import-related income',
                'is_main' => true,
            ],
            // Contra Revenue
            [
                'name' => "Sales Returns & Allowances",
                'slug' => 'sales-returns-allowances',
                'remark' => 'Returns and discounts given',
                'is_main' => true,
            ],
            [
                'name' => "Discounts Given",
                'slug' => 'discounts-given',
                'remark' => 'Discounts offered to customers',
                'is_main' => true,
            ],

            // ================= EXPENSES =================
            [
                'name' => "Cost of Goods Sold",
                'slug' => 'cost-of-goods-sold',
                'remark' => 'Direct cost of products sold',
                'is_main' => true,
            ],
            [
                'name' => "Cost of Sales",
                'slug' => 'cost-of-sales',
                'remark' => 'Cost related to sales',
                'is_main' => true,
            ],
            // Operating Expenses
            [
                'name' => "Expenses (Direct)",
                'slug' => 'expenses-direct',
                'remark' => 'Direct manufacturing/trading expenses',
                'is_main' => true,
            ],
            [
                'name' => "Expenses (Indirect)",
                'slug' => 'expenses-indirect',
                'remark' => 'Administrative expenses',
                'is_main' => true,
            ],
            [
                'name' => "Office Expenses",
                'slug' => 'office-expenses',
                'remark' => 'General office expenses',
                'is_main' => true,
            ],
            [
                'name' => "Employee Benefit Expense",
                'slug' => 'employee-benefit-expense',
                'remark' => 'Salaries and benefits',
                'is_main' => true,
            ],
            [
                'name' => "Expenditure Account",
                'slug' => 'expenditure-account',
                'remark' => 'General expenditure',
                'is_main' => true,
            ],
            [
                'name' => "Sarafi",
                'slug' => 'sarafi',
                'remark' => 'Miscellaneous expenses (local term)',
                'is_main' => true,
            ],
            [
                'name' => "Store",
                'slug' => 'store',
                'remark' => 'Store-related expenses',
                'is_main' => true,
            ],
            [
                'name' => "Freight & Shipping",
                'slug' => 'freight-shipping',
                'remark' => 'Transportation costs',
                'is_main' => true,
            ],
            // Loss/Write-off Expenses
            [
                'name' => "Bad Debts & Wastage",
                'slug' => 'bad-debts-wastage',
                'remark' => 'Uncollectible debts and wastage',
                'is_main' => true,
            ],
            [
                'name' => "Self Breakage/Expired/Wastage",
                'slug' => 'self-breakage-expired-wastage',
                'remark' => 'Inventory loss from breakage/expiry',
                'is_main' => true,
            ],
            [
                'name' => "Depreciation A/C",
                'slug' => 'depreciation-ac',
                'remark' => 'Depreciation expense',
                'is_main' => true,
            ],
            // Contra Expenses
            [
                'name' => "Discounts Received",
                'slug' => 'discounts-received',
                'remark' => 'Discounts from suppliers',
                'is_main' => true,
            ],

            // ================= GAINS/LOSSES/ADJUSTMENTS =================
            [
                'name' => "Gains/Losses",
                'slug' => 'gains-losses',
                'remark' => 'Capital gains and losses',
                'is_main' => true,
            ],
            [
                'name' => "Short & Excess",
                'slug' => 'short-excess',
                'remark' => 'Inventory discrepancies',
                'is_main' => true,
            ],
            [
                'name' => "Round Off",
                'slug' => 'round-off',
                'remark' => 'Rounding differences',
                'is_main' => true,
            ],
            [
                'name' => "Negative Value of Bill",
                'slug' => 'negative-value-of-bill',
                'remark' => 'Negative billing adjustments',
                'is_main' => true,
            ],
            [
                'name' => "Stock-In-Hand",
                'slug' => 'stock-in-hand',
                'remark' => 'Physical inventory adjustments',
                'is_main' => true,
            ],
            [
                'name' => "Store T/F",
                'slug' => 'store-transfer',
                'remark' => 'Store transfer adjustments',
                'is_main' => true,
            ],
        ];
    }


    /**
     * Relationship to accounts that use this account type
     */
    public function accounts()
    {
        return $this->hasMany(\App\Models\Account\Account::class, 'account_type_id');
    }
}
