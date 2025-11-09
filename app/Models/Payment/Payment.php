<?php

namespace App\Models\Payment;

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

class Payment extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $fillable = [
        'number',
        'date',
        'ledger_id',
        'payment_transaction_id',
        'bank_transaction_id',
        'cheque_no',
        'description',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'ledger_id' => 'string',
        'payment_transaction_id' => 'string',
        'bank_transaction_id' => 'string',
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
            'description',
        ];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_transaction_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bank_transaction_id');
    }

    protected function getRelationships(): array
    {
        return [
            'paymentTransaction' => [
                'model' => 'transactions',
                'message' => 'This payment has a linked payment transaction',
            ],
            'bankTransaction' => [
                'model' => 'transactions',
                'message' => 'This payment has a linked bank transaction',
            ],
        ];
    }
}


