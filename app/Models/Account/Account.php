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
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

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

                if ($totalDebit > $totalCredit) {
                    $balanceAmount = $totalDebit - $totalCredit;
                    $balanceNature = 'dr';
                } elseif ($totalCredit > $totalDebit) {
                    $balanceAmount = $totalCredit - $totalDebit;
                    $balanceNature = 'cr';
                } else {
                    $balanceAmount = 0;
                    $balanceNature = '';
                }

                $netBalance = $totalDebit - $totalCredit;

                return [
                    'balance'               => $balanceAmount,
                    'balance_nature'        => $balanceNature,
                    'balance_with_nature'   => $balanceAmount>0?$balanceAmount . '.' . $balanceNature:0,
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


    public static function defaultAccounts(): array
{
    return [
        // ================= ASSETS =================
            [
                'name' => 'Cash in Hand',
                'number' => '1010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
                'account_type_slug' => 'cash-or-bank',
                'slug' => 'cash-in-hand',
                'remark' => 'Physical cash available',
                'is_main' => true,
            ],
            [
                'name' => 'Cash in Safe',
                'number' => '1020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
                'account_type_slug' => 'cash-or-bank',
                'slug' => 'cash-in-safe',
                'remark' => 'Physical cash available',
                'is_main' => true,
            ],
            [
                'name' => 'Cash in Bank',
                'number' => '1030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
                'account_type_slug' => 'cash-or-bank',
                'slug' => 'cash-in-bank',
                'remark' => 'Cash in bank accounts',
                'is_main' => true,
            ],
            [
                'name' => 'Petty Cash',
                'number' => '1040',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cash-or-bank')->first()->id,
                'account_type_slug' => 'cash-or-bank',
                'slug' => 'petty-cash',
                'remark' => 'Petty cash available',
                'is_main' => true,
            ],
            [
                'name' => 'Accounts Receivable',
                'number' => '2010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'account-receivable')->first()->id,
                'account_type_slug' => 'account-receivable',
                'slug' => 'accounts-receivable',
                'remark' => 'Money owed by customers',
                'is_main' => true,
            ],
            [
                'name' => 'Undeposited Money',
                'number' => '3010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'undeposited-money',
                'remark' => 'Undeposited money',
                'is_main' => true,
            ],
            [
                'name' => 'Inventory Stock',
                'number' => '3020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'inventory-stock',
                'remark' => 'Inventory stock',
                'is_main' => true,
            ],
            [
                'name' => 'Non-Inventory items',
                'number' => '3021',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'non-inventory-items',
                'remark' => 'Non-inventory items',
                'is_main' => true,
            ],
            [
                'name' => 'Raw materials inventory',
                'number' => '3040',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'raw-materials',
                'remark' => 'Raw materials',
                'is_main' => true,
            ],
            [
                'name' => 'Finished goods inventory',
                'number' => '3050',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'finished-goods',
                'remark' => 'Finished goods',
                'is_main' => true,
            ],
            [
                'name' => 'Advances and Prepaid/Diposit',
                'number' => '3030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-asset')->first()->id,
                'account_type_slug' => 'other-current-asset',
                'slug' => 'advances-prepaid-deposit',
                'remark' => 'Advances and prepaid/diposit',
                'is_main' => true,
            ],
            [
                'name' => 'Fixed Assets',
                'number' => '4000',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'fixed-assets',
                'remark' => 'Fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Land & Building',
                'number' => '4010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'land-building',
                'remark' => 'Land and building',
                'is_main' => true,
            ],
            [
                'name' => 'Machinery & Equipments',
                'number' => '4020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'machinery-equipments',
                'remark' => 'Machinery and equipments',
                'is_main' => true,
            ],
            [
                'name' => 'Vehicles & Generators',
                'number' => '4030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'vehicles-generators',
                'remark' => 'Vehicles and generators',
                'is_main' => true,
            ],
            [
                'name' => 'Furnitures & Fixtures',
                'number' => '4040',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'furnitures-fixtures',
                'remark' => 'Furnitures and fixtures',
                'is_main' => true,
            ],
            [
                'name' => 'Computers & Phone Related Items',
                'number' => '4050',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'computers-phone-items',
                'remark' => 'Computers and phone items related to fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Kitchen Utensils & Misc. Tools',
                'number' => '4060',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'kitchen-utensils-misc-tools',
                'remark' => 'Kitchen utensils and misc. tools related to fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Accumulated Depreciation',
                'number' => '4070',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'fixed-asset')->first()->id,
                'account_type_slug' => 'fixed-asset',
                'slug' => 'accumulated-depreciation',
                'remark' => 'Accumulated depreciation of fixed assets',
                'is_main' => true,
            ],
            [
                'name' => 'Accounts Payable',
                'number' => '5010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'accounts-payable')->first()->id,
                'account_type_slug' => 'accounts-payable',
                'slug' => 'accounts-payable',
                'remark' => 'Money owed to suppliers',
                'is_main' => true,
            ],
            [
                'name' => 'Current Liabilities',
                'number' => '5080',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'current-liabilities',
                'remark' => 'Current liabilities',
                'is_main' => true,
            ],
            [
                'name' => 'Payroll Liabilities',
                'number' => '5081',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'payroll-liabilities',
                'remark' => 'Payroll liabilities',
                'is_main' => true,
            ],
            [
                'name' => 'Tax Liabilities',
                'number' => '5082',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'tax-liabilities',
                'remark' => 'Tax liabilities',
                'is_main' => true,
            ],
            [
                'name' => 'Other Accrued Expenses',
                'number' => '5083',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'other-current-liability')->first()->id,
                'account_type_slug' => 'other-current-liability',
                'slug' => 'other-accrued-expenses',
                'remark' => 'Other accrued expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Loan',
                    'number' => '5090',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'long-term-liability')->first()->id,
                'account_type_slug' => 'long-term-liability',
                'slug' => 'loan',
                'remark' => 'Loan',
                'is_main' => true,
            ],
            [
                'name' => 'Opening Balance Equity',
                'number' => '6000',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'opening-balance-equity',
                'remark' => 'Opening balance equity',
                'is_main' => true,
            ],
            [
                'name' => 'Owners Equity',
                'number' => '6010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owners-equity',
                'remark' => 'Owners equity',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 1 Contribution',
                'number' => '6011',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-1-contribution',
                'remark' => 'Owner 1 contribution to the business',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 2 Contribution',
                'number' => '6012',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-2-contribution',
                'remark' => 'Owner 2 contribution to the business',
                'is_main' => true,
            ],
            [
                'name' => 'Owners Draw',
                'number' => '6020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owners-draw',
                'remark' => 'Owners draw',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 1 Draw',
                    'number' => '6021',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-1-draw',
                'remark' => 'Owner 1 draw',
                'is_main' => true,
            ],
            [
                'name' => 'Owner 1 Draw',
                'number' => '6022',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'owner-1-draw-2',
                'remark' => 'Owner 1 draw 2',
                'is_main' => true,
            ],
            [
                'name' => 'Reatined Earnings',
                'number' => '6030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'equity')->first()->id,
                'account_type_slug' => 'equity',
                'slug' => 'retained-earnings',
                'remark' => 'Retained earnings',
                'is_main' => true,
            ],
            [
                'name' => 'Income',
                'number' => '7000',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'income',
                'remark' => 'Income',
                'is_main' => true,
            ],
            [
                'name' => 'Product Income',
                'number' => '7010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'product-income',
                'remark' => 'Product income',
                'is_main' => true,
            ],
            [
                'name' => 'Service Revenues',
                'number' => '7020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'income')->first()->id,
                'account_type_slug' => 'income',
                'slug' => 'service-revenues',
                'remark' => 'Service revenues',
                'is_main' => true,
            ],
            [
                'name' => 'Cost of Goods Sold',
                'number' => '8000',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'cost-of-goods-sold',
                'remark' => 'Cost of goods sold',
                'is_main' => true,
            ],
            [
                'name' => 'Direct Material Cost',
                'number' => '8010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'direct-material-cost',
                'remark' => 'Direct material cost',
                'is_main' => true,
            ],
            [
                'name' => 'Direct Working Cost',
                'number' => '8020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'cost-of-goods-sold')->first()->id,
                'account_type_slug' => 'cost-of-goods-sold',
                'slug' => 'direct-working-cost',
                'remark' => 'Direct working cost of goods sold',
                'is_main' => true,
            ],
            [
                'name' => 'Admin Expenses',
                'number' => '9100',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'admin-expenses',
                'remark' => 'Admin expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Office Rent',
                    'number' => '9101',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'office-rent',
                'remark' => 'Office rent',
                'is_main' => true,
            ],
            [
                'name' => 'Kitchen Food/Refreshment',
                'number' => '9102',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'kitchen-food-refreshment',
                'remark' => 'Kitchen food/refreshment',
                'is_main' => true,
            ],
            [
                'name' => 'Site Food and Hospitality',
                'number' => '9103',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'site-food-hospitality',
                'remark' => 'Site food and hospitality',
                'is_main' => true,
            ],
            [
                'name' => 'Office Supplies/Equipments',
                'number' => '9104',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'office-supplies-equipments',
                'remark' => 'Office supplies/equipments',
                'is_main' => true,
            ],
            [
                'name' => 'Office Stationary',
                'number' => '9105',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'office-stationary',
                'remark' => 'Office stationary',
                'is_main' => true,
            ],
            [
                'name' => 'Advertising/Promotion',
                'number' => '9106',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'advertising-promotion',
                'remark' => 'Advertising/promotion',
                'is_main' => true,
            ],
            [
                'name' => 'Employee Recruitment/Training',
                'number' => '9107',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'employee-recruitment-training',
                'remark' => 'Employee recruitment/training',
                'is_main' => true,
            ],
            [
                'name' => 'License Permit and Legal Fee',
                'number' => '9108',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'license-permit-legal-fee',
                'remark' => 'License permit and legal fee',
                'is_main' => true,
            ],
            [
                'name' => 'Payroll Expenses',
                'number' => '9200',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'payroll-expenses',
                'remark' => 'Payroll expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Permanent Staff Salary',
                'number' => '9201',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'permanent-staff-salary',
                'remark' => 'Permanent staff salary expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Temporary Staff Salary',
                'number' => '9202',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'temporary-staff-salary',
                'remark' => 'Temporary staff salary expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Consultant/Professional Salary',
                'number' => '9207',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'consultant-professional-salary',
                'remark' => 'Consultant/professional salary expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Allowances & Commissions',
                    'number' => '9208',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'allowances-commissions',
                'remark' => 'Allowances and commissions',
                'is_main' => true,
            ],
            [
                'name' => 'Operational Expenses',
                'number' => '9300',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'operational-expenses',
                'remark' => 'Operational expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Vehicle Expense',
                'number' => '9301',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'vehicle-expense',
                'remark' => 'Vehicle expense',
                'is_main' => true,
            ],
            [
                'name' => 'Generator Expense',
                'number' => '9302',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'generator-expense',
                'remark' => 'Generator expense',
                'is_main' => true,
            ],
            [
                'name' => 'Building Repair/Maintenance',
                'number' => '9303',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'building-repair-maintenance',
                'remark' => 'Building repair/maintenance',
                'is_main' => true,
            ],
            [
                'name' => 'Transportation/Taxi Fare',
                'number' => '9304',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'transportation-taxi-fare',
                'remark' => 'Transportation/taxi fare',
                'is_main' => true,
            ],
            [
                'name' => 'Travel/ Visa/ Postage',
                'number' => '9305',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'travel-visa-postage',
                'remark' => 'Travel/visa/postage',
                'is_main' => true,
            ],
            [
                'name' => 'Safety & Mediacal Supplies',
                'number' => '9306',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'safety-medical-supplies',
                'remark' => 'Safety & medical supplies',
                'is_main' => true,
            ],
            [
                'name' => 'Utilities Expenses',
                'number' => '9400',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'utilities-expenses',
                'remark' => 'Utilities expenses (electricity, water, internet)',
                'is_main' => true,
            ],
            [
                'name' => 'Gas Expense',
                'number' => '9401',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'gas-expense',
                'remark' => 'Gas expense',
                'is_main' => true,
            ],
            [
                'name' => 'Electricity Bill/Genset Fuel',
                'number' => '9402',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'electricity-bill-genset-fuel',
                'remark' => 'Electricity bill/genset fuel',
                'is_main' => true,
            ],
            [
                'name' => 'Internet Bill',
                'number' => '9403',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'internet-bill',
                'remark' => 'Internet bill',
                'is_main' => true,
            ],
            [
                'name' => 'Telephone Expense',
                'number' => '9404',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'telephone-expense',
                'remark' => 'Telephone expense',
                'is_main' => true,
            ],
            [
                'name' => 'Software/Website/Email Hosting',
                'number' => '9405',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'software-website-email-hosting',
                'remark' => 'Software/website/email hosting',
                'is_main' => true,
            ],
            [
                'name' => 'Cleaning Supplies/Trash Removal',
                'number' => '9406',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'cleaning-supplies-trash-removal',
                'remark' => 'Cleaning supplies/trash removal',
                'is_main' => true,
            ],
            [
                'name' => 'Heating Expense',
                'number' => '9407',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'heating-expense',
                'remark' => 'Heating expense',
                'is_main' => true,
            ],
            [
                'name' => 'Other Expenses',
                'number' => '9500',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'other-expenses',
                'remark' => 'Other expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Depreciation Expense',
                'number' => '9501',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'depreciation-expense',
                'remark' => 'Depreciation expense',
                'is_main' => true,
            ],
            [
                'name' => 'Bank Service Charges',
                'number' => '9502',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'bank-service-charges',
                'remark' => 'Bank service charges',
                'is_main' => true,
            ],
            [
                'name' => 'Bad Debts',
                'number' => '9503',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'bad-debts',
                'remark' => 'Bad debts and wastage',
                'is_main' => true,
            ],
            [
                'name' => 'Insurance Expense',
                'number' => '9504',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'insurance-expense',
                'remark' => 'Insurance expense',
                'is_main' => true,
            ],
            [
                'name' => 'Interest Expense',
                'number' => '9506',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'interest-expense',
                'remark' => 'Interest expense',
                'is_main' => true,
            ],
            [
                'name' => 'Misc. Supplies and Services',
                'number' => '9507',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'misc-supplies-services',
                'remark' => 'Misc. supplies and services',
                'is_main' => true,
            ],
            [
                'name' => 'Tax Related Expenses',
                'number' => '9600',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'tax-related-expenses',
                'remark' => 'Tax related expenses',
                'is_main' => true,
            ],
            [
                'name' => 'Business Receipt Tax BRT',
                'number' => '9601',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'business-receipt-tax-brt',
                'remark' => 'Business receipt tax BRT',
                'is_main' => true,
            ],
            [
                'name' => 'Salary Withholding Tax',
                'number' => '9602',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'salary-withholding-tax',
                'remark' => 'Salary withholding tax',
                'is_main' => true,
            ],
            [
                'name' => 'Rent Withholding Tax',
                'number' => '9603',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'rent-withholding-tax',
                'remark' => 'Rent withholding tax',
                'is_main' => true,
            ],
            [
                'name' => 'Contractor Withholding Tax',
                'number' => '9604',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'contractor-withholding-tax',
                'remark' => 'Contractor withholding tax',
                'is_main' => true,
            ],
            [
                'name' => 'Income Tax',
                'number' => '9605',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'income-tax',
                'remark' => 'Income tax',
                'is_main' => true,
            ],
            [
                'name' => 'Exchange Gain or Loss',
                'number' => '9700',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'exchange-gain-loss',
                'remark' => 'Exchange gain or loss',
                'is_main' => true,
            ],
            [
                'name' => 'Ask My Accountant',
                'number' => '9800',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'expense')->first()->id,
                'account_type_slug' => 'expense',
                'slug' => 'ask-my-accountant',
                'remark' => 'Ask my accountant fee',
                'is_main' => true,
            ],
            [
                'name' => 'Purchase Orders',
                'number' => '9010',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
                'account_type_slug' => 'non-posting',
                'slug' => 'purchase-orders',
                'remark' => 'Purchase orders',
                'is_main' => true,
            ],
            [
                'name' => 'Sales Orders',
                'number' => '9020',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
                'account_type_slug' => 'non-posting',
                'slug' => 'sales-orders',
                'remark' => 'Sales orders',
                'is_main' => true,
            ],
            [
                'name' => 'Quotation',
                'number' => '9030',
                'account_type_id' => AccountType::withoutGlobalScopes()->where('slug', 'non-posting')->first()->id,
                'account_type_slug' => 'non-posting',
                'slug' => 'quotation',
                'remark' => '',
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
