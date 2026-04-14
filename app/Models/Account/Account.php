<?php

namespace App\Models\Account;

use App\Models\Ledger\LedgerOpening;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
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

                $netBalance = $totalDebit>0 ? $totalDebit - $totalCredit : $totalCredit - $totalDebit;
                $balanceAmount = abs($netBalance);
                $balanceNature = $netBalance > 0
                    ? 'dr'
                    : ($netBalance < 0 ? 'cr' : null);

                $natureFormat = balanceNatureFormat();

                // Format based on system setting
                if ($natureFormat === 'with_nature') {
                    $formattedBalance = $balanceAmount > 0
                        ? $balanceAmount . ' ' . $balanceNature
                        : 0;
                } elseif ($natureFormat === 'without_nature') {
                    $formattedBalance = $netBalance; // signed
                } else { // with_balance (absolute only)
                    $formattedBalance = $balanceAmount;
                }

                return [
                    'balance'               => $formattedBalance,
                    'balance_amount'        => $balanceAmount,
                    'balance_nature'        => $balanceNature,
                    'balance_with_nature'   => $balanceAmount > 0
                        ? $balanceAmount . ' ' . $balanceNature
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

    protected function getOpeningTransactionId(): ?string
    {
        if ($this->relationLoaded('opening')) {
            return $this->opening?->transaction_id;
        }

        return $this->opening()->value('transaction_id');
    }

    public function nonOpeningTransactionLines()
    {
        $openingTransactionId = $this->getOpeningTransactionId();

        return TransactionLine::query()
            ->where('account_id', $this->id)
            ->when(
                $openingTransactionId,
                fn ($query) => $query->where('transaction_id', '!=', $openingTransactionId)
            );
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
    public function getDependencies(): array
    {
        $dependencies = [];

        $childrenCount = $this->children()->count();
        if ($childrenCount > 0) {
            $dependencies[] = [
                'relation' => 'children',
                'count' => $childrenCount,
                'model' => 'accounts',
                'message' => 'This account has children accounts',
            ];
        }

        $transactionCount = $this->nonOpeningTransactionLines()
            ->distinct('transaction_id')
            ->count('transaction_id');

        if ($transactionCount > 0) {
            $dependencies[] = [
                'relation' => 'transactions',
                'count' => $transactionCount,
                'model' => 'transactions',
                'message' => 'This account is used in transactions',
            ];
        }

        return $dependencies;
    }

    protected function getRelationships(): array
    {
        return [
            'children' => [
                'model' => 'accounts',
                'message' => 'This account has children accounts'
            ],
        ];
    }

    public function getAccountsByAccountTypeSlug(string $slug)
    {
        $locale = app()->getLocale();
        return $this->withoutGlobalScopes()->whereHas('accountType', function ($query) use ($slug, $locale) {
            $query->where('slug', $slug);
        })->get()->map(function ($account) use ($locale) {
            return [
                'id' => $account->id,
                'number' => $account->number,
                'slug' => $account->slug,
                'name' => $locale === 'en' ? $account->name : ($account->local_name ?? $account->name),
                'english_name' => $account->name,
                'local_name' => $account->local_name,
            ];
        });
    }

    public static function defaultAccounts(): array
    {
        return [
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
                'remark' => 'Physical cash available',
                'is_main' => true,
            ],
            [
                'name' => 'Cash in Bank',
                'local_name' => 'موجودی حساب‌های بانکی',
                'number' => '1030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
                'account_type_slug' => 'cash-or-bank',
                'slug' => 'cash-in-bank',
                'remark' => 'Cash in bank accounts',
                'is_main' => true,
            ],
            [
                'name' => 'Petty Cash',
                'local_name' => 'تنخواه گردان',
                'number' => '1040',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
                'account_type_slug' => 'cash-or-bank',
                'slug' => 'petty-cash',
                'remark' => 'Petty cash available',
                'is_main' => true,
            ],
            [
                'name' => 'Accounts Receivable',
                'local_name' => 'حساب‌های دریافتنی',
                'number' => '2010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'account-receivable')->first()->id,
                'account_type_slug' => 'account-receivable',
                'slug' => 'account-receivable',
                'remark' => 'Money owed by customers',
                'is_main' => true,
            ],
            [
                'name' => 'Undeposited Money',
                'local_name' => 'وجوه واریز نشده',
                'number' => '3010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'undeposited-money',
                'remark' => 'Undeposited money',
                'is_main' => true,
            ],
            [
                'name' => 'Inventory Stock',
                'local_name' => 'موجودی انبار کالا',
                'number' => '3020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'inventory-stock',
                'remark' => 'Inventory stock',
                'is_main' => true,
            ],
            [
                'name' => 'Non-Inventory items',
                'local_name' => 'کالاهای غیرانبارشی',
                'number' => '3021',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'non-inventory-items',
                'remark' => 'Non-inventory items',
                'is_main' => true,
            ],
            [
                'name' => 'Raw materials inventory',
                'local_name' => 'موجودی مواد اولیه',
                'number' => '3040',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'raw-materials',
                'remark' => 'Raw materials',
                'is_main' => true,
            ],
            [
                'name' => 'Finished goods inventory',
                'local_name' => 'موجودی کالای ساخته‌شده',
                'number' => '3050',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'finished-goods',
                'remark' => 'Finished goods',
                'is_main' => true,
            ],
            [
                'name' => 'Advances and Prepaid/Diposit',
                'local_name' => 'پیش‌پرداخت‌ها و سپرده‌ها',
                'number' => '3030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'advances-prepaid-deposit',
                'remark' => 'Advances and prepaid/diposit',
                'is_main' => true,
            ],
            [
                'name' => 'Fixed Assets',
                'local_name' => 'دارایی‌های ثابت',
                'number' => '4000',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'fixed-assets',
                'remark' => 'Fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Land & Building',
                'local_name' => 'زمین و ساختمان',
                'number' => '4010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'land-building',
                'remark' => 'Land and building',
                'is_main' => true,
            ],
            [
                'name' => 'Machinery & Equipments',
                'local_name' => 'ماشین‌آلات و تجهیزات',
                'number' => '4020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'machinery-equipments',
                'remark' => 'Machinery and equipments',
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
                'name' => 'Furnitures & Fixtures',
                'local_name' => 'اثاثیه و ملحقات',
                'number' => '4040',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'furnitures-fixtures',
                'remark' => 'Furnitures and fixtures',
                'is_main' => true,
            ],
            [
                'name' => 'Computers & Phone Related Items',
                'local_name' => 'کمپیوتر و تجهیزات مخابراتی',
                'number' => '4050',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'computers-phone-items',
                'remark' => 'Computers and phone items related to fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Kitchen Utensils & Misc. Tools',
                'local_name' => 'لوازم آشپزخانه و ابزار متفرقه',
                'number' => '4060',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'kitchen-utensils-misc-tools',
                'remark' => 'Kitchen utensils and misc. tools related to fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Accumulated Depreciation',
                'local_name' => 'استهلاک انباشته',
                'number' => '4070',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'accumulated-depreciation',
                'remark' => 'Accumulated depreciation of fixed assets',
                'is_main' => true,
            ],

            // LIABILITIES
            [
                'name' => 'Accounts Payable',
                'local_name' => 'حساب‌های پرداختنی',
                'number' => '5010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'account-payable')->first()->id,
                'account_type_slug' => 'account-payable',
                'slug' => 'account-payable',
                'remark' => 'Money owed to suppliers',
                'is_main' => true,
            ],
            [
                'name' => 'Current Liabilities',
                'local_name' => 'بدهی‌های جاری',
                'number' => '5080',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'current-liabilities',
                'remark' => 'Current liabilities',
                'is_main' => true,
            ],
            [
                'name' => 'Payroll Liabilities',
                'local_name' => 'بدهی‌های حقوق و معاش',
                'number' => '5081',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'payroll-liabilities',
                'remark' => 'Payroll liabilities',
                'is_main' => true,
            ],
            [
                'name' => 'Tax Liabilities',
                'local_name' => 'بدهی‌های مالیاتی',
                'number' => '5082',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'tax-liabilities',
                'remark' => 'Tax liabilities',
                'is_main' => true,
            ],
            [
                'name' => 'Other Accrued Expenses',
                'local_name' => 'هزینه‌های تعهدشده',
                'number' => '5083',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'other-accrued-expenses',
                'remark' => 'Other accrued expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Loan',
                'local_name' => 'وام دریافتی',
                'number' => '5090',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'long-term-liability')->first()->id,
                'account_type_slug' => 'long-term-liability',
                'slug' => 'loan',
                'remark' => 'Loan',
                'is_main' => true,
            ],

            // EQUITY
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
                'name' => 'Owner 1 Contribution',
                'local_name' => 'سهم‌الشرکه مالک اول',
                'number' => '6011',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-1-contribution',
                'remark' => 'Owner 1 contribution to the business',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 2 Contribution',
                'local_name' => 'سهم‌الشرکه مالک دوم',
                'number' => '6012',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-2-contribution',
                'remark' => 'Owner 2 contribution to the business',
                'is_main' => true,
            ],
            [
                'name' => 'Owners Draw',
                'local_name' => 'برداشت مالکین',
                'number' => '6020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owners-draw',
                'remark' => 'Owners draw',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 1 Draw',
                'local_name' => 'برداشت مالک اول',
                'number' => '6021',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-1-draw',
                'remark' => 'Owner 1 draw',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 2 Draw',
                'local_name' => 'برداشت مالک دوم',
                'number' => '6022',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-2-draw',
                'remark' => 'Owner 2 draw',
                'is_main' => true,
            ],
            [
                'name' => 'Reatined Earnings',
                'local_name' => 'سود انباشته',
                'number' => '6030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'retained-earnings',
                'remark' => 'Retained earnings',
                'is_main' => true,
            ],

            // INCOME
            [
                'name' => 'Income',
                'local_name' => 'درآمد',
                'number' => '7000',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'income',
                'remark' => 'Income',
                'is_main' => true,
            ],
            [
                'name' => 'Product Income',
                'local_name' => 'درآمد فروش کالا',
                'number' => '7010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'product-income',
                'remark' => 'Product income',
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
            [
                'name' => 'Discount to Customer',
                'local_name' => 'تخفیف به مشتری',
                'number' => '7030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'discount-to-customer',
                'remark' => 'Discount to customer',
                'is_main' => true,
            ],


            // COGS
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
                'name' => 'Direct Material Cost',
                'local_name' => 'هزینه مستقیم مواد اولیه',
                'number' => '8010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'direct-material-cost',
                'remark' => 'Direct material cost',
                'is_main' => true,
            ],
            [
                'name' => 'Direct Working Cost',
                'local_name' => 'هزینه مستقیم دستمزد',
                'number' => '8020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'direct-working-cost',
                'remark' => 'Direct working cost of goods sold',
                'is_main' => true,
            ],
            [
                'name' => 'Discount from Supplier',
                'local_name' => 'تخفیف از فروشنده',
                'number' => '8030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'discount-from-supplier',
                'remark' => 'Discount from supplier',
                'is_main' => true,
            ],

            // EXPENSES - ADMIN
            [
                'name' => 'Admin Expenses',
                'local_name' => 'هزینه‌های اداری',
                'number' => '9100',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'admin-expenses',
                'remark' => 'Admin expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Office Rent',
                'local_name' => 'کرایه دفتر',
                'number' => '9101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'office-rent',
                'remark' => 'Office rent',
                'is_main' => true,
            ],
            [
                'name' => 'Kitchen Food/Refreshment',
                'local_name' => 'مصارف خوراک و پذیرایی',
                'number' => '9102',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'kitchen-food-refreshment',
                'remark' => 'Kitchen food/refreshment',
                'is_main' => true,
            ],
            [
                'name' => 'Site Food and Hospitality',
                'local_name' => 'مصارف پذیرایی ساحه کاری',
                'number' => '9103',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'site-food-hospitality',
                'remark' => 'Site food and hospitality',
                'is_main' => true,
            ],
            [
                'name' => 'Office Supplies/Equipments',
                'local_name' => 'لوازم و تجهیزات اداری',
                'number' => '9104',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'office-supplies-equipments',
                'remark' => 'Office supplies/equipments',
                'is_main' => true,
            ],
            [
                'name' => 'Office Stationary',
                'local_name' => 'قرطاسیه اداری',
                'number' => '9105',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'office-stationary',
                'remark' => 'Office stationary',
                'is_main' => true,
            ],
            [
                'name' => 'Advertising/Promotion',
                'local_name' => 'هزینه تبلیغات و ترویج',
                'number' => '9106',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'advertising-promotion',
                'remark' => 'Advertising/promotion',
                'is_main' => true,
            ],
            [
                'name' => 'Employee Recruitment/Training',
                'local_name' => 'هزینه جذب و آموزش کارمندان',
                'number' => '9107',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'employee-recruitment-training',
                'remark' => 'Employee recruitment/training',
                'is_main' => true,
            ],
            [
                'name' => 'License Permit and Legal Fee',
                'local_name' => 'هزینه جواز و خدمات حقوقی',
                'number' => '9108',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'license-permit-legal-fee',
                'remark' => 'License permit and legal fee',
                'is_main' => true,
            ],

            // EXPENSES - PAYROLL
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
                'name' => 'Permanent Staff Salary',
                'local_name' => 'حقوق کارمندان دایمی',
                'number' => '9201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'permanent-staff-salary',
                'remark' => 'Permanent staff salary expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Temporary Staff Salary',
                'local_name' => 'حقوق کارمندان موقت',
                'number' => '9202',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'temporary-staff-salary',
                'remark' => 'Temporary staff salary expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Consultant/Professional Salary',
                'local_name' => 'حق‌الزحمه مشاورین',
                'number' => '9207',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'consultant-professional-salary',
                'remark' => 'Consultant/professional salary expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Allowances & Commissions',
                'local_name' => 'امتیازات و کمیسیون‌ها',
                'number' => '9208',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'allowances-commissions',
                'remark' => 'Allowances and commissions',
                'is_main' => true,
            ],

            // EXPENSES - OPERATIONAL
            [
                'name' => 'Operational Expenses',
                'local_name' => 'هزینه‌های عملیاتی',
                'number' => '9300',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'operational-expenses',
                'remark' => 'Operational expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Vehicle Expense',
                'local_name' => 'هزینه وسایط نقلیه',
                'number' => '9301',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'vehicle-expense',
                'remark' => 'Vehicle expense',
                'is_main' => true,
            ],
            [
                'name' => 'Generator Expense',
                'local_name' => 'هزینه جنراتور',
                'number' => '9302',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'generator-expense',
                'remark' => 'Generator expense',
                'is_main' => true,
            ],
            [
                'name' => 'Building Repair/Maintenance',
                'local_name' => 'ترمیم و نگهداری ساختمان',
                'number' => '9303',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'building-repair-maintenance',
                'remark' => 'Building repair/maintenance',
                'is_main' => true,
            ],
            [
                'name' => 'Transportation/Taxi Fare',
                'local_name' => 'هزینه ترانسپورت',
                'number' => '9304',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'transportation-taxi-fare',
                'remark' => 'Transportation/taxi fare',
                'is_main' => true,
            ],
            [
                'name' => 'Travel/ Visa/ Postage',
                'local_name' => 'هزینه سفر / ویزه / پُست',
                'number' => '9305',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'travel-visa-postage',
                'remark' => 'Travel/visa/postage',
                'is_main' => true,
            ],
            [
                'name' => 'Safety & Mediacal Supplies',
                'local_name' => 'لوازم ایمنی و طبی',
                'number' => '9306',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'safety-medical-supplies',
                'remark' => 'Safety & medical supplies',
                'is_main' => true,
            ],

            // UTILITIES
            [
                'name' => 'Utilities Expenses',
                'local_name' => 'مصارف خدمات عمومی',
                'number' => '9400',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'utilities-expenses',
                'remark' => 'Utilities expenses (electricity, water, internet)',
                'is_main' => true,
            ],
            [
                'name' => 'Gas Expense',
                'local_name' => 'هزینه گاز',
                'number' => '9401',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'gas-expense',
                'remark' => 'Gas expense',
                'is_main' => true,
            ],
            [
                'name' => 'Electricity Bill/Genset Fuel',
                'local_name' => 'برق / تیل جنراتور',
                'number' => '9402',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'electricity-bill-genset-fuel',
                'remark' => 'Electricity bill/genset fuel',
                'is_main' => true,
            ],
            [
                'name' => 'Internet Bill',
                'local_name' => 'هزینه انترنت',
                'number' => '9403',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'internet-bill',
                'remark' => 'Internet bill',
                'is_main' => true,
            ],
            [
                'name' => 'Telephone Expense',
                'local_name' => 'هزینه تلفن',
                'number' => '9404',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'telephone-expense',
                'remark' => 'Telephone expense',
                'is_main' => true,
            ],
            [
                'name' => 'Software/Website/Email Hosting',
                'local_name' => 'هزینه نرم‌افزار / وبسایت / هاست ایمیل',
                'number' => '9405',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'software-website-email-hosting',
                'remark' => 'Software/website/email hosting',
                'is_main' => true,
            ],
            [
                'name' => 'Cleaning Supplies/Trash Removal',
                'local_name' => 'هزینه تنظیفات / انتقال زباله',
                'number' => '9406',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'cleaning-supplies-trash-removal',
                'remark' => 'Cleaning supplies/trash removal',
                'is_main' => true,
            ],
            [
                'name' => 'Heating Expense',
                'local_name' => 'هزینه گرمایش',
                'number' => '9407',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'heating-expense',
                'remark' => 'Heating expense',
                'is_main' => true,
            ],

            // OTHER EXPENSES
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
                'name' => 'Bank Service Charges',
                'local_name' => 'کارمزد بانکی',
                'number' => '9502',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'bank-service-charges',
                'remark' => 'Bank service charges',
                'is_main' => true,
            ],
            [
                'name' => 'Bad Debts',
                'local_name' => 'مطالبات سوخت‌شده',
                'number' => '9503',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'bad-debts',
                'remark' => 'Bad debts and wastage',
                'is_main' => true,
            ],
            [
                'name' => 'Insurance Expense',
                'local_name' => 'هزینه بیمه',
                'number' => '9504',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'insurance-expense',
                'remark' => 'Insurance expense',
                'is_main' => true,
            ],
            [
                'name' => 'Interest Expense',
                'local_name' => 'هزینه بهره',
                'number' => '9506',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'interest-expense',
                'remark' => 'Interest expense',
                'is_main' => true,
            ],
            [
                'name' => 'Misc. Supplies and Services',
                'local_name' => 'لوازم و خدمات متفرقه',
                'number' => '9507',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'misc-supplies-services',
                'remark' => 'Misc. supplies and services',
                'is_main' => true,
            ],

            // TAX RELATED
            [
                'name' => 'Tax Related Expenses',
                'local_name' => 'هزینه‌های مرتبط به مالیات',
                'number' => '9600',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'tax-related-expenses',
                'remark' => 'Tax related expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Business Receipt Tax BRT',
                'local_name' => 'مالیات بر عواید کاروباری (BRT)',
                'number' => '9601',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'business-receipt-tax-brt',
                'remark' => 'Business receipt tax BRT',
                'is_main' => true,
            ],
            [
                'name' => 'Salary Withholding Tax',
                'local_name' => 'مالیات تکلیفی معاش',
                'number' => '9602',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'salary-withholding-tax',
                'remark' => 'Salary withholding tax',
                'is_main' => true,
            ],
            [
                'name' => 'Rent Withholding Tax',
                'local_name' => 'مالیات تکلیفی کرایه',
                'number' => '9603',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'rent-withholding-tax',
                'remark' => 'Rent withholding tax',
                'is_main' => true,
            ],
            [
                'name' => 'Contractor Withholding Tax',
                'local_name' => 'مالیات تکلیفی قراردادی',
                'number' => '9604',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'contractor-withholding-tax',
                'remark' => 'Contractor withholding tax',
                'is_main' => true,
            ],
            [
                'name' => 'Income Tax',
                'local_name' => 'مالیات بر درآمد',
                'number' => '9605',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'income-tax',
                'remark' => 'Income tax',
                'is_main' => true,
            ],

            // MISC
            [
                'name' => 'Exchange Gain or Loss',
                'local_name' => 'سود یا زیان تسعیر ارز',
                'number' => '9700',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'exchange-gain-loss',
                'remark' => 'Exchange gain or loss',
                'is_main' => true,
            ],
            [
                'name' => 'Ask My Accountant',
                'local_name' => 'هزینه مشاوره حسابداری',
                'number' => '9800',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'ask-my-accountant',
                'remark' => 'Ask my accountant fee',
                'is_main' => true,
            ],

            // NON-POSTING
            [
                'name' => 'Purchase Orders',
                'local_name' => 'سفارشات خرید',
                'number' => '9010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
                'account_type_slug' => 'non-posting',
                'slug' => 'purchase-orders',
                'remark' => 'Purchase orders',
                'is_main' => true,
            ],
            [
                'name' => 'Sales Orders',
                'local_name' => 'سفارشات فروش',
                'number' => '9020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
                'account_type_slug' => 'non-posting',
                'slug' => 'sales-orders',
                'remark' => 'Sales orders',
                'is_main' => true,
            ],
            [
                'name' => 'Quotation',
                'local_name' => 'پیشنهاد قیمت',
                'number' => '9030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
                'account_type_slug' => 'non-posting',
                'slug' => 'quotation',
                'remark' => '',
                'is_main' => true,
            ],

        ];
    }
}
