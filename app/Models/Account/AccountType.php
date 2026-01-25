<?php

namespace App\Models\Account;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasBranch;
use App\Traits\BranchSpecific;
class AccountType extends Model
{
    use HasFactory, HasSearch, HasSorting,HasUlids, BranchSpecific, HasBranch, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $table = 'account_types';
    protected $keyType = 'string';
    public $incrementing = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [ 
        'name',
        'nature',
        'branch_id',
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
        'branch_id' => 'string',
        'slug' => 'string',
        'nature' => 'string',
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
                    'name' => "Cash or Bank",
                    'slug' => 'cash-or-bank',
                    'nature' => 'asset',
                    'remark' => 'Cash and bank accounts',
                    'is_main' => true,
                ],
                [
                    'name' => "Account Receivable",
                    'slug' => 'account-receivable',
                    'nature' => 'asset',
                    'remark' => 'Money owed by customers',
                    'is_main' => true,
                ],
                [
                    'name' => "Other Current Asset",
                    'slug' => 'other-current-asset',
                    'nature' => 'asset',
                    'remark' => 'Other short-term assets',
                    'is_main' => true,
                ],
                [
                    'name' => "Fixed Asset",
                    'slug' => 'fixed-asset',
                    'nature' => 'asset',
                    'remark' => 'Long-term tangible assets',
                    'is_main' => true,
                ],
                [
                    'name' => "Accounts Payable",
                    'slug' => 'accounts-payable',
                    'nature' => 'liability',
                    'remark' => 'Money owed to suppliers',
                    'is_main' => true,
                ],
                [
                    'name' => "Other Current Liability",
                    'slug' => 'other-current-liability',
                    'nature' => 'liability',
                    'remark' => 'Other short-term liabilities',
                    'is_main' => true,
                ],
                [
                    'name' => "Long Term Liability",
                    'slug' => 'long-term-liability',
                    'nature' => 'liability',
                    'remark' => 'Long-term debts and obligations',
                    'is_main' => true,
                ],
                [
                    'name' => "Equity",
                    'slug' => 'equity',
                    'nature' => 'equity',
                    'remark' => 'Ownership interests',
                    'is_main' => true,
                ],
                [
                    'name' => "Income",
                    'slug' => 'income',
                    'nature' => 'income',
                    'remark' => 'Revenue and income',
                    'is_main' => true,
                ],
                [
                    'name' => "Cost of Goods Sold",
                    'slug' => 'cost-of-goods-sold',
                    'nature' => 'expense',
                    'remark' => 'Direct costs of sales',
                    'is_main' => true,
                ],
                [
                    'name' => "Expense",
                    'slug' => 'expense',
                    'nature' => 'expense',
                    'remark' => 'General expenses',
                    'is_main' => true,
                ],
                [
                    'name' => "Non-Posting",
                    'slug' => 'non-posting',
                    'nature' => 'non-posting',
                    'remark' => 'Non-posting/summary type',
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
