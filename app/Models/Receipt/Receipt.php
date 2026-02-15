<?php

namespace App\Models\Receipt;

use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
class Receipt extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $fillable = [
        'number',
        'date',
        'ledger_id',
        'transaction_id',
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
        'transaction_id' => 'string',
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }


    protected function getRelationships(): array
    {
        return [
            'transaction' => [
                'model' => 'transactions',
                'message' => 'This receipt has a linked transaction',
            ],
        ];
    }
}


