<?php

namespace App\Models\Account;

use App\Models\Ledger\LedgerOpening;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Models\Administration\Branch;
use App\Traits\BranchSpecific;

class Account extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'number',
        'account_type_id',
        'branch_id',
        'slug',
        'is_active',
        'is_main',
        'tenant_id',
        'remark',
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
        'account_type_id' => 'string',
        'branch_id' => 'string',
        'slug' => 'string',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'tenant_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'number',
            'remark',
        ];
    }


    public static function defaultAccounts(): array
{
    return [
        // ================= ASSETS =================
            [
                'name' => 'Cash In Hand',
                'number' => '1001',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash')->first()->id,
                'account_type_slug' => 'cash',
                'slug' => 'cash',
                'remark' => 'Physical cash available',
                'is_main' => true,
            ],
            [
                'name' => 'Bank AFN',
                'number' => '1002',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'bank-account')->first()->id,
                'account_type_slug' => 'bank-account',
                'slug' => 'bank-afn',
                'remark' => 'AFN currency bank account',
                'is_main' => true,
            ],
            [
                'name' => 'Bank USD',
                'number' => '1003',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'bank-account')->first()->id,
                'account_type_slug' => 'bank-account',
                'slug' => 'bank-usd',
                'remark' => 'USD currency bank account',
                'is_main' => true,
            ],
            [
                'name' => 'Accounts Receivable',
                'number' => '1101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'account-receivable')->first()->id,
                'account_type_slug' => 'account-receivable',
                'slug' => 'accounts-receivable',
                'remark' => 'Money owed by customers',
                'is_main' => true,
            ],
            [
                'name' => 'Inventory',
                'number' => '1201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'inventory')->first()->id,
                'account_type_slug' => 'inventory',
                'slug' => 'inventory',
                'remark' => 'Goods held for sale',
                'is_main' => true,
            ],
            [
                'name' => 'Prepaid Expenses',
                'number' => '1301',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'prepaid-expenses')->first()->id,
                'account_type_slug' => 'prepaid-expenses',
                'slug' => 'prepaid-expenses',
                'remark' => 'Expenses paid in advance',
                'is_main' => true,
            ],
            [
                'name' => 'Loans Receivable',
                'number' => '1401',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'loan-advance-asset')->first()->id,
                'account_type_slug' => 'loan-advance-asset',
                'slug' => 'loans-receivable',
                'remark' => 'Loans given to others',
                'is_main' => true,
            ],
            [
                'name' => 'Security Deposits',
                'number' => '1501',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'security-and-deposit-asset')->first()->id,
                'account_type_slug' => 'security-and-deposit-asset',
                'slug' => 'security-deposits',
                'remark' => 'Security deposits paid',
                'is_main' => true,
            ],
            // Contra Asset
            [
                'name' => 'Allowance for Doubtful Accounts',
                'number' => '1901',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'allowance-doubtful-accounts')->first()->id,
                'account_type_slug' => 'allowance-doubtful-accounts',
                'slug' => 'allowance-doubtful-accounts',
                'remark' => 'Estimated uncollectible receivables',
                'is_main' => true,
            ],

            // ================= LIABILITIES =================
            [
                'name' => 'Accounts Payable',
                'number' => '2001',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'account-payable')->first()->id,
                'account_type_slug' => 'account-payable',
                'slug' => 'accounts-payable',
                'remark' => 'Money owed to suppliers',
                'is_main' => true,
            ],
            [
                'name' => 'Sales Tax Payable',
                'number' => '2101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'sales-tax')->first()->id,
                'account_type_slug' => 'sales-tax',
                'slug' => 'sales-tax-payable',
                'remark' => 'Sales tax collected payable',
                'is_main' => true,
            ],
            [
                'name' => 'Accrued Expenses',
                'number' => '2201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'accrued-expenses')->first()->id,
                'account_type_slug' => 'accrued-expenses',
                'slug' => 'accrued-expenses',
                'remark' => 'Expenses incurred but not paid',
                'is_main' => true,
            ],
            [
                'name' => 'Deferred Revenue',
                'number' => '2301',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'deferred-revenue')->first()->id,
                'account_type_slug' => 'deferred-revenue',
                'slug' => 'deferred-revenue',
                'remark' => 'Advance payments from customers',
                'is_main' => true,
            ],
            [
                'name' => 'Bank Loan',
                'number' => '2401',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'loans-liability')->first()->id,
                'account_type_slug' => 'loans-liability',
                'slug' => 'bank-loan',
                'remark' => 'Bank loans payable',
                'is_main' => true,
            ],

            // ================= EQUITY =================
            [
                'name' => "Owner's Capital",
                'number' => '3001',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'capital-account')->first()->id,
                'account_type_slug' => 'capital-account',
                'slug' => 'owners-capital',
                'remark' => 'Owner capital contributions',
                'is_main' => true,
            ],
            [
                'name' => "Owner's Drawings",
                'number' => '3002',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'drawings')->first()->id,
                'account_type_slug' => 'drawings',
                'slug' => 'owners-drawings',
                'remark' => 'Owner personal withdrawals',
                'is_main' => true,
            ],
            [
                'name' => 'Retained Earnings',
                'number' => '3003',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'retained-earnings')->first()->id,
                'account_type_slug' => 'retained-earnings',
                'slug' => 'retained-earnings',
                'remark' => 'Accumulated profits/losses',
                'is_main' => true,
            ],
            [
                'name' => 'Opening Balance Equity',
                'number' => '3004',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'opening-balance-equity')->first()->id,
                'account_type_slug' => 'opening-balance-equity',
                'slug' => 'opening-balance-equity',
                'remark' => 'Initial business equity',
                'is_main' => true,
            ],
            [
                'name' => 'Current Year Earnings',
                'number' => '3005',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'profit-loss-ac')->first()->id,
                'account_type_slug' => 'profit-loss-ac',
                'slug' => 'current-year-earnings',
                'remark' => 'Current year profit/loss',
                'is_main' => true,
            ],

            // ================= INCOME =================
            [
                'name' => 'Sales Revenue',
                'number' => '4001',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'sales-revenue')->first()->id,
                'account_type_slug' => 'sales-revenue',
                'slug' => 'sales-revenue',
                'remark' => 'Revenue from sales',
                'is_main' => true,
            ],
            [
                'name' => 'Service Income',
                'number' => '4101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'service-income',
                'remark' => 'Income from services',
                'is_main' => true,
            ],
            [
                'name' => 'Interest Income',
                'number' => '4201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'indirect-income')->first()->id,
                'account_type_slug' => 'indirect-income',
                'slug' => 'interest-income',
                'remark' => 'Interest earned',
                'is_main' => true,
            ],
            // Contra Revenue
            [
                'name' => 'Sales Returns & Allowances',
                'number' => '4901',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'sales-returns-allowances')->first()->id,
                'account_type_slug' => 'sales-returns-allowances',
                'slug' => 'sales-returns-allowances',
                'remark' => 'Returns and discounts given',
                'is_main' => true,
            ],
            [
                'name' => 'Discounts Given',
                'number' => '4902',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'discounts-given')->first()->id,
                'account_type_slug' => 'discounts-given',
                'slug' => 'discounts-given',
                'remark' => 'Discounts offered to customers',
                'is_main' => true,
            ],

            // ================= EXPENSES =================
            [
                'name' => 'Cost of Goods Sold',
                'number' => '5001',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'cost-of-goods-sold',
                'remark' => 'Direct cost of products sold',
                'is_main' => true,
            ],
            [
                'name' => 'Office Expenses',
                'number' => '5101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'office-expenses')->first()->id,
                'account_type_slug' => 'office-expenses',
                'slug' => 'office-expenses',
                'remark' => 'General office expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Salaries & Wages',
                'number' => '5201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'employee-benefit-expense')->first()->id,
                'account_type_slug' => 'employee-benefit-expense',
                'slug' => 'salaries-wages',
                'remark' => 'Employee salaries and wages',
                'is_main' => true,
            ],
            [
                'name' => 'Rent Expense',
                'number' => '5301',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expenses-indirect')->first()->id,
                'account_type_slug' => 'expenses-indirect',
                'slug' => 'rent-expense',
                'remark' => 'Rental payments',
                'is_main' => true,
            ],
            [
                'name' => 'Utilities Expense',
                'number' => '5401',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expenses-indirect')->first()->id,
                'account_type_slug' => 'expenses-indirect',
                'slug' => 'utilities-expense',
                'remark' => 'Electricity, water, internet',
                'is_main' => true,
            ],
            [
                'name' => 'Marketing Expense',
                'number' => '5501',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expenses-indirect')->first()->id,
                'account_type_slug' => 'expenses-indirect',
                'slug' => 'marketing-expense',
                'remark' => 'Advertising and promotion',
                'is_main' => true,
            ],
            [
                'name' => 'Freight & Shipping',
                'number' => '5601',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'freight-shipping')->first()->id,
                'account_type_slug' => 'freight-shipping',
                'slug' => 'freight-shipping',
                'remark' => 'Transportation costs',
                'is_main' => true,
            ],
            [
                'name' => 'Sarafi',
                'number' => '5701',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'sarafi')->first()->id,
                'account_type_slug' => 'sarafi',
                'slug' => 'sarafi',
                'remark' => 'Miscellaneous expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Depreciation Expense',
                'number' => '5801',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'depreciation-ac')->first()->id,
                'account_type_slug' => 'depreciation-ac',
                'slug' => 'depreciation-expense',
                'remark' => 'Depreciation of fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Bad Debts Expense',
                'number' => '5901',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'bad-debts-wastage')->first()->id,
                'account_type_slug' => 'bad-debts-wastage',
                'slug' => 'bad-debts-expense',
                'remark' => 'Uncollectible accounts',
                'is_main' => true,
            ],
            // Contra Expense
            [
                'name' => 'Discounts Received',
                'number' => '5991',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'discounts-received')->first()->id,
                'account_type_slug' => 'discounts-received',
                'slug' => 'discounts-received',
                'remark' => 'Discounts from suppliers',
                'is_main' => true,
            ],

            // ================= OTHER =================
            [
                'name' => 'Gains/Losses on Sale',
                'number' => '6001',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'gains-losses')->first()->id,
                'account_type_slug' => 'gains-losses',
                    'slug' => 'gains-losses-on-sale',
                'remark' => 'Gains or losses on asset sales',
                'is_main' => true,
            ],
            [
                'name' => 'Inventory Adjustments',
                'number' => '6101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'short-excess')->first()->id,
                'account_type_slug' => 'short-excess',
                'slug' => 'inventory-adjustments',
                'remark' => 'Inventory discrepancies',
                'is_main' => true,
            ],
            [
                'name' => 'Round Off',
                'number' => '6201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'round-off')->first()->id,
                'account_type_slug' => 'round-off',
                'slug' => 'round-off',
                'remark' => 'Rounding differences',
                'is_main' => true,
            ],
        ];
    }

    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function opening()
    {
        return $this->morphOne(LedgerOpening::class, 'ledgerable');
    }

    public function openings()
    {
        return $this->morphMany(LedgerOpening::class, 'ledgerable');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'transactions' => [
                'model' => 'transactions',
                'message' => 'This account is used in transactions'
            ]
        ];
    }
}
