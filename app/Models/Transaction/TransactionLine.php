<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Account\Account;
use App\Models\Ledger\Ledger;
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
        'debit',
        'credit',
        'bill_number',
        'remark',
        'deleted_by',
    ];

    public function casts(): array
    {
        return [
            'id' => 'string',
            'transaction_id' => 'string',
            'account_id' => 'string',
            'ledger_id' => 'string',
            'debit' => 'float',
            'credit' => 'float',
        ];
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
}
