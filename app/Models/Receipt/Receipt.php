<?php

namespace App\Models\Receipt;

use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Receipt extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $fillable = [
        'number',
        'date',
        'ledger_id',
        'receive_transaction_id',
        'bank_transaction_id',
        'cheque_no',
        'narration',
        'currency_id',
        'rate',
        'amount',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'ledger_id' => 'string',
        'receive_transaction_id' => 'string',
        'bank_transaction_id' => 'string',
        'currency_id' => 'string',
        'rate' => 'float',
        'amount' => 'float',
        'date' => 'date',
        'created_by' => 'string',
        'updated_by' => 'string',
        'branch_id' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'number',
            'date',
            'cheque_no',
            'narration',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function receiveTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'receive_transaction_id');
    }
    

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bank_transaction_id');
    }

    protected function getRelationships(): array
    {
        return [
            'receiveTransaction' => [
                'model' => 'transactions',
                'message' => 'This receipt has a linked receive transaction',
            ],
            'bankTransaction' => [
                'model' => 'transactions',
                'message' => 'This receipt has a linked bank transaction',
            ],
        ];
    }
}


