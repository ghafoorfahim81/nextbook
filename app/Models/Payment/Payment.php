<?php

namespace App\Models\Payment;

use App\Enums\PaymentMode;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Payment extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $fillable = [
        'number',
        'date',
        'ledger_id',
        'payment_mode',
        'cheque_no',
        'narration',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'ledger_id' => 'string',
        'payment_mode' => PaymentMode::class,
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

    protected array $allowedFilters = [
        'ledger_id',
        'transaction.currency_id',
        'transaction.lines.account_id',
        'date',
        'created_by',
    ];

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    public function purchasePayments(): HasMany
    {
        return $this->hasMany(\App\Models\Purchase\PurchasePayment::class);
    }

    protected function getRelationships(): array
    {
        return [
            'transaction' => [
                'model' => 'transactions',
                'message' => 'This payment has a linked transaction',
            ],
        ];
    }
}

