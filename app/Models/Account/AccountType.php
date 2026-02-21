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
                    'name' => "نقد و بانک (Cash or Bank)",
                    'slug' => 'cash-or-bank',
                    'nature' => 'asset',
                    'remark' => 'وجوه نقد در صندوق و موجودی حساب‌های بانکی (Cash on hand and bank balances)',
                    'is_main' => true,
                ],
                [
                    'name' => "حساب‌های دریافتنی (Accounts Receivable)",
                    'slug' => 'account-receivable',
                    'nature' => 'asset',
                    'remark' => 'مطالبات شرکت از مشتریان بابت فروش نسیه (Amounts owed by customers)',
                    'is_main' => true,
                ],
                [
                    'name' => "سایر دارایی‌های جاری (Other Current Asset)",
                    'slug' => 'other-current-asset',
                    'nature' => 'asset',
                    'remark' => 'سایر دارایی‌های کوتاه‌مدت که در چرخه عملیاتی مصرف می‌شوند (Other short-term assets)',
                    'is_main' => true,
                ],
                [
                    'name' => "دارایی‌های ثابت (Fixed Asset)",
                    'slug' => 'fixed-asset',
                    'nature' => 'asset',
                    'remark' => 'دارایی‌های بلندمدت مشهود مورد استفاده در عملیات شرکت (Long-term tangible assets)',
                    'is_main' => true,
                ],
                [
                    'name' => "حساب‌های پرداختنی (Accounts Payable)",
                    'slug' => 'account-payable',
                    'nature' => 'liability',
                    'remark' => 'بدهی شرکت به تأمین‌کنندگان بابت خرید نسیه (Amounts owed to suppliers)',
                    'is_main' => true,
                ],
                [
                    'name' => "سایر بدهی‌های جاری (Other Current Liability)",
                    'slug' => 'other-current-liability',
                    'nature' => 'liability',
                    'remark' => 'سایر تعهدات کوتاه‌مدت قابل پرداخت (Other short-term liabilities)',
                    'is_main' => true,
                ],
                [
                    'name' => "بدهی‌های بلندمدت (Long Term Liability)",
                    'slug' => 'long-term-liability',
                    'nature' => 'liability',
                    'remark' => 'بدهی‌ها و تعهدات قابل پرداخت در بلندمدت (Long-term debts and obligations)',
                    'is_main' => true,
                ],
                [
                    'name' => "حقوق صاحبان سهام (Equity / Capital)",
                    'slug' => 'equity',
                    'nature' => 'equity',
                    'remark' => 'سرمایه مالکین و سود انباشته شرکت (Owners’ equity and retained earnings)',
                    'is_main' => true,
                ],
                [
                    'name' => "درآمد (Income)",
                    'slug' => 'income',
                    'nature' => 'income',
                    'remark' => 'عواید حاصل از فعالیت‌های عملیاتی و غیرعملیاتی (Revenue and other income)',
                    'is_main' => true,
                ],
                [
                    'name' => "بهای تمام‌شده کالای فروش‌رفته (Cost of Goods Sold)",
                    'slug' => 'cost-of-goods-sold',
                    'nature' => 'expense',
                    'remark' => 'هزینه مستقیم کالاهای فروخته‌شده (Direct cost of goods sold)',
                    'is_main' => true,
                ],
                [
                    'name' => "هزینه‌ها (Expense)",
                    'slug' => 'expense',
                    'nature' => 'expense',
                    'remark' => 'هزینه‌های عملیاتی، اداری و عمومی شرکت (Operating and general expenses)',
                    'is_main' => true,
                ],
                [
                    'name' => "غیرقابل ثبت (Non-Posting)",
                    'slug' => 'non-posting',
                    'nature' => 'non-posting',
                    'remark' => 'حساب‌های نمایشی یا تجمیعی که قابلیت ثبت مستقیم ندارند (Summary or non-posting accounts)',
                    'is_main' => true,
                ]
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
