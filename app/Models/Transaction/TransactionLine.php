<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\JournalEntry\JournalClass;
use App\Traits\CompanySpecific;
use App\Traits\HasCompanyFromParent;
class TransactionLine extends Model
{
    // CompanySpecific but not BranchSpecific: a line's branch comes from its
    // voucher, while its company has to be on the row itself — the composite
    // foreign key to `accounts` is what stops a line of one company pointing at
    // another company's chart of accounts, and that needs the column here.
    use HasFactory, HasUlids, CompanySpecific, HasCompanyFromParent, SoftDeletes;

    protected $table = 'transaction_lines';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'company_id',
        'transaction_id',
        'account_id',
        'ledger_id',
        'journal_class_id',
        // Phase 7: the line's own branch (null: the voucher's) and cost center.
        'branch_id',
        'cost_center_id',
        'currency_id',
        'rate',
        'debit',
        'credit',
        'base_debit',
        'base_credit',
        'remark',
        'remark_fa',
        'remark_ps',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function casts(): array
    {
        return [
            'id' => 'string',
            'transaction_id' => 'string',
            'account_id' => 'string',
            'ledger_id' => 'string',
            'journal_class_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'currency_id' => 'string',
            'rate' => 'float',
            'debit' => 'float',
            'credit' => 'float',
            'base_debit' => 'float',
            'base_credit' => 'float',
        ];
    }

    /**
     * The currency this line is denominated in.
     *
     * Deliberately has NO fallback to the header currency or header rate. A
     * line's rate is set once, at posting time, by TransactionService. If it
     * is missing that is a data bug to surface, not to paper over — a silent
     * fallback is how a receivable ends up carrying the settlement-date rate
     * instead of its original booking rate.
     */
    public function currency()
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class, 'currency_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }
    public function journalClass()
    {
        return $this->belongsTo(JournalClass::class, 'journal_class_id');
    }

}
