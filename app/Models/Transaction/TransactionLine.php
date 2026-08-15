<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
use App\Models\JournalEntry\JournalClass;
class TransactionLine extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'transaction_lines';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'transaction_id',
        'account_id',
        'ledger_id',
        'journal_class_id',
        'currency_id',
        'rate',
        'debit',
        'credit',
        'base_debit',
        'base_credit',
        'remark',
        'remark_fa',
        'remark_ps',
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
