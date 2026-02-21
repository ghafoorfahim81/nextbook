<?php

namespace App\Models\Account;

use App\Models\Ledger\LedgerOpening;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use App\Models\Administration\Branch;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

class Account extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'local_name',
        'number',
        'account_type_id',
        'parent_id',
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
        'local_name' => 'string',
        'account_type_id' => 'string',
        'parent_id' => 'string',
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
            'local_name',
            'number',
            'remark',
        ];
    }

    protected array $allowedFilters = [
        'local_name',
        'number',
        'name',
        'account_type_id',
        'accountType.name',
        'is_active',
        'created_by',
        'createdBy.name',
    ];

    protected function statement(): Attribute
    {
        return Attribute::make(
            get: function () {

                $totals = $this->transactionLines()
                    ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                    ->where('transactions.status', 'posted')
                    ->selectRaw('
                        SUM(transaction_lines.debit * transactions.rate)  AS total_debit,
                        SUM(transaction_lines.credit * transactions.rate) AS total_credit
                    ')
                    ->first();

                $totalDebit  = (float) ($totals->total_debit ?? 0);
                $totalCredit = (float) ($totals->total_credit ?? 0);

                // ALWAYS calculate net this way
                $netBalance = $totalDebit - $totalCredit;

                // Determine real balance nature from math
                if ($netBalance > 0) {
                    $balanceNature = 'dr';
                } elseif ($netBalance < 0) {
                    $balanceNature = 'cr';
                } else {
                    $balanceNature = null;
                }

                $balanceAmount = abs($netBalance);

                $natureFormat = balanceNatureFormat();

                // Format based on system setting
                if ($natureFormat === 'with_nature') {
                    $formattedBalance = $balanceAmount > 0
                        ? $balanceAmount . '.' . $balanceNature
                        : 0;
                } elseif ($natureFormat === 'without_nature') {
                    $formattedBalance = $netBalance; // signed
                } else { // with_balance (absolute only)
                    $formattedBalance = $balanceAmount;
                }

                return [
                    'balance'               => $formattedBalance,
                    'balance_nature'        => $balanceNature,
                    'balance_with_nature'   => $balanceAmount > 0
                        ? $balanceAmount . '.' . $balanceNature
                        : 0,
                    'total_debit'           => $totalDebit,
                    'total_credit'          => $totalCredit,
                    'net_balance'           => $netBalance,
                    'normal_balance_nature' => $this->normal_balance ?? '',
                    'is_normal_balance'     => $this->isNormalBalance($netBalance),
                ];
            }
        );
    }

    public function isNormalBalance(float $netBalance): bool
    {
        if ($this->normal_balance === 'dr') {
            return $netBalance >= 0;
        }

        return $netBalance <= 0;
    }


    public function accountType(): BelongsTo
    {
        return $this->belongsTo(AccountType::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactionLines()
    {
        return $this->hasMany(
            \App\Models\Transaction\TransactionLine::class,
            'account_id'
        );
    }

    public function transactions()
    {
        return $this->belongsToMany(
            Transaction::class,
            'transaction_lines',
            'account_id',
            'transaction_id'
        )
            ->withPivot(['debit', 'credit', 'remark'])
            ->withTimestamps()
            ->distinct();
    }


    public function opening()
    {
        return $this->morphOne(LedgerOpening::class, 'ledgerable');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_id');
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
            ],
            'children' => [
                'model' => 'accounts',
                'message' => 'This account has children accounts'
            ],
            'parent' => [
                'model' => 'accounts',
                'message' => 'This account has a parent account'
            ]
        ];
    }

    public function getAccountsByAccountTypeSlug(string $slug)
    {
        return $this->whereHas('accountType', function ($query) use ($slug) {
            $query->where('slug', $slug);
        })->get();
    }

    public static function defaultAccounts(): array
{
    return [

        /*
        |--------------------------------------------------------------------------
        | ASSETS - CASH & BANK
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Cash in Hand',
            'local_name' => 'نقد در صندوق',
            'number' => '1010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
            'account_type_slug' => 'cash-or-bank',
            'slug' => 'cash-in-hand',
            'remark' => 'Physical cash available',
            'is_main' => true,
        ],
        [
            'name' => 'Cash in Safe',
            'local_name' => 'نقد در گاوصندوق',
            'number' => '1020',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
            'account_type_slug' => 'cash-or-bank',
            'slug' => 'cash-in-safe',
            'remark' => 'Physical cash stored securely',
            'is_main' => true,
        ],
        [
            'name' => 'Cash in Bank',
            'local_name' => 'موجودی حساب‌های بانکی',
            'number' => '1030',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
            'account_type_slug' => 'cash-or-bank',
            'slug' => 'cash-in-bank',
            'remark' => 'Cash available in bank accounts',
            'is_main' => true,
        ],
        [
            'name' => 'Petty Cash',
            'local_name' => 'تنخواه گردان',
            'number' => '1040',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
            'account_type_slug' => 'cash-or-bank',
            'slug' => 'petty-cash',
            'remark' => 'Small operational cash fund',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | RECEIVABLES
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Accounts Receivable',
            'local_name' => 'حساب‌های دریافتنی',
            'number' => '2010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'account-receivable')->first()->id,
            'account_type_slug' => 'account-receivable',
            'slug' => 'accounts-receivable',
            'remark' => 'Money owed by customers',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | INVENTORY & CURRENT ASSETS
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Undeposited Money',
            'local_name' => 'وجوه واریز نشده',
            'number' => '3010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
            'account_type_slug' => 'other-current-asset',
            'slug' => 'undeposited-money',
            'remark' => 'Receipts not yet deposited',
            'is_main' => true,
        ],
        [
            'name' => 'Inventory Stock',
            'local_name' => 'موجودی انبار کالا',
            'number' => '3020',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
            'account_type_slug' => 'other-current-asset',
            'slug' => 'inventory-stock',
            'remark' => 'Inventory available for sale',
            'is_main' => true,
        ],
        [
            'name' => 'Non-Inventory Items',
            'local_name' => 'کالاهای غیرانبارشی',
            'number' => '3021',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
            'account_type_slug' => 'other-current-asset',
            'slug' => 'non-inventory-items',
            'remark' => 'Items not tracked in stock',
            'is_main' => true,
        ],
        [
            'name' => 'Raw Materials Inventory',
            'local_name' => 'موجودی مواد اولیه',
            'number' => '3040',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
            'account_type_slug' => 'other-current-asset',
            'slug' => 'raw-materials',
            'remark' => 'Raw materials inventory',
            'is_main' => true,
        ],
        [
            'name' => 'Finished Goods Inventory',
            'local_name' => 'موجودی کالای ساخته‌شده',
            'number' => '3050',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
            'account_type_slug' => 'other-current-asset',
            'slug' => 'finished-goods',
            'remark' => 'Finished goods inventory',
            'is_main' => true,
        ],
        [
            'name' => 'Advances and Prepaid/Deposit',
            'local_name' => 'پیش‌پرداخت‌ها و سپرده‌ها',
            'number' => '3030',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
            'account_type_slug' => 'other-current-asset',
            'slug' => 'advances-prepaid-deposit',
            'remark' => 'Advances and prepaid expenses',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | FIXED ASSETS
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Fixed Assets',
            'local_name' => 'دارایی‌های ثابت',
            'number' => '4000',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
            'account_type_slug' => 'fixed-asset',
            'slug' => 'fixed-assets',
            'remark' => 'Fixed assets category',
            'is_main' => true,
        ],
        [
            'name' => 'Land & Building',
            'local_name' => 'زمین و ساختمان',
            'number' => '4010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
            'account_type_slug' => 'fixed-asset',
            'slug' => 'land-building',
            'remark' => 'Land and buildings',
            'is_main' => true,
        ],
        [
            'name' => 'Machinery & Equipment',
            'local_name' => 'ماشین‌آلات و تجهیزات',
            'number' => '4020',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
            'account_type_slug' => 'fixed-asset',
            'slug' => 'machinery-equipments',
            'remark' => 'Machinery and equipment',
            'is_main' => true,
        ],
        [
            'name' => 'Vehicles & Generators',
            'local_name' => 'وسایط نقلیه و جنراتورها',
            'number' => '4030',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
            'account_type_slug' => 'fixed-asset',
            'slug' => 'vehicles-generators',
            'remark' => 'Vehicles and generators',
            'is_main' => true,
        ],
        [
            'name' => 'Accumulated Depreciation',
            'local_name' => 'استهلاک انباشته',
            'number' => '4070',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
            'account_type_slug' => 'fixed-asset',
            'slug' => 'accumulated-depreciation',
            'remark' => 'Accumulated depreciation',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | EQUITY
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Opening Balance Equity',
            'local_name' => 'سرمایه افتتاحیه',
            'number' => '6000',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
            'account_type_slug' => 'equity',
            'slug' => 'opening-balance-equity',
            'remark' => 'Opening balance equity',
            'is_main' => true,
        ],
        [
            'name' => 'Owners Equity',
            'local_name' => 'سرمایه مالکین',
            'number' => '6010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
            'account_type_slug' => 'equity',
            'slug' => 'owners-equity',
            'remark' => 'Owners equity',
            'is_main' => true,
        ],
        [
            'name' => 'Retained Earnings',
            'local_name' => 'سود انباشته',
            'number' => '6030',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
            'account_type_slug' => 'equity',
            'slug' => 'retained-earnings',
            'remark' => 'Retained earnings',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | INCOME
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Income',
            'local_name' => 'درآمد',
            'number' => '7000',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
            'account_type_slug' => 'income',
            'slug' => 'income',
            'remark' => 'Income category',
            'is_main' => true,
        ],
        [
            'name' => 'Product Income',
            'local_name' => 'درآمد فروش کالا',
            'number' => '7010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
            'account_type_slug' => 'income',
            'slug' => 'product-income',
            'remark' => 'Product sales income',
            'is_main' => true,
        ],
        [
            'name' => 'Service Revenues',
            'local_name' => 'درآمد خدمات',
            'number' => '7020',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
            'account_type_slug' => 'income',
            'slug' => 'service-revenues',
            'remark' => 'Service revenues',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | EXPENSES
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Cost of Goods Sold',
            'local_name' => 'بهای تمام‌شده کالای فروش‌رفته',
            'number' => '8000',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
            'account_type_slug' => 'cost-of-goods-sold',
            'slug' => 'cost-of-goods-sold',
            'remark' => 'Cost of goods sold',
            'is_main' => true,
        ],
        [
            'name' => 'Admin Expenses',
            'local_name' => 'هزینه‌های اداری',
            'number' => '9100',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
            'account_type_slug' => 'expense',
            'slug' => 'admin-expenses',
            'remark' => 'Administrative expenses',
            'is_main' => true,
        ],
        [
            'name' => 'Office Rent',
            'local_name' => 'کرایه دفتر',
            'number' => '9101',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
            'account_type_slug' => 'expense',
            'slug' => 'office-rent',
            'remark' => 'Office rent expense',
            'is_main' => true,
        ],
        [
            'name' => 'Payroll Expenses',
            'local_name' => 'هزینه حقوق و معاش',
            'number' => '9200',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
            'account_type_slug' => 'expense',
            'slug' => 'payroll-expenses',
            'remark' => 'Payroll expenses',
            'is_main' => true,
        ],
        [
            'name' => 'Depreciation Expense',
            'local_name' => 'هزینه استهلاک',
            'number' => '9501',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
            'account_type_slug' => 'expense',
            'slug' => 'depreciation-expense',
            'remark' => 'Depreciation expense',
            'is_main' => true,
        ],
        [
            'name' => 'Other Expenses',
            'local_name' => 'سایر هزینه‌ها',
            'number' => '9500',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
            'account_type_slug' => 'expense',
            'slug' => 'other-expenses',
            'remark' => 'Other expenses',
            'is_main' => true,
        ],

        /*
        |--------------------------------------------------------------------------
        | NON-POSTING
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Purchase Orders',
            'local_name' => 'سفارشات خرید',
            'number' => '9010',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
            'account_type_slug' => 'non-posting',
            'slug' => 'purchase-orders',
            'remark' => 'Purchase orders (non-posting)',
            'is_main' => true,
        ],
        [
            'name' => 'Sales Orders',
            'local_name' => 'سفارشات فروش',
            'number' => '9020',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
            'account_type_slug' => 'non-posting',
            'slug' => 'sales-orders',
            'remark' => 'Sales orders (non-posting)',
            'is_main' => true,
        ],
        [
            'name' => 'Quotation',
            'local_name' => 'پیشنهاد قیمت',
            'number' => '9030',
            'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
            'account_type_slug' => 'non-posting',
            'slug' => 'quotation',
            'remark' => 'Quotation document (non-posting)',
            'is_main' => true,
        ],
    ];
    }
}