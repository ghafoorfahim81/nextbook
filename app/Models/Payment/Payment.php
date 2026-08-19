<?php

namespace App\Models\Payment;

use App\Enums\PaymentMode;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Accounting\Settlement;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Traits\HasAttachments;
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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Payment extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, HasDependencyCheck, HasAttachments, HasSequentialNumber, SoftDeletes;

    protected $fillable = [
        'number',
        'date',
        'ledger_id',
        'payment_mode',
        'cheque_no',
        'narration',
        'status',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'ledger_id' => 'string',
        'payment_mode' => PaymentMode::class,
        'status' => 'string',
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
            'payment_mode',
            'ledger.name',
            'transaction.currency.name',
        ];
    }

    protected array $allowedFilters = [
        'ledger_id',
        'payment_mode',
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

    /**
     * What this payment settled, and at which rates. The mirror of
     * Receipt::settlements() — same table, payable side.
     */
    public function settlements(): HasManyThrough
    {
        return $this->hasManyThrough(
            Settlement::class,
            Transaction::class,
            'reference_id',
            'transaction_id',
            'id',
            'id'
        )->where('transactions.reference_type', self::class);
    }

    /**
     * The line that carries the money actually paid out.
     *
     * By account TYPE, not by "the first credit" — a realised exchange gain is
     * also a credit on this voucher, and line order is not guaranteed. Mirror
     * of Receipt::cashLine().
     */
    public function cashLine()
    {
        return $this->transaction?->lines
            ?->first(fn ($line) => $line->account?->accountType?->slug === 'cash-or-bank');
    }

    /** What was paid, in the voucher's own currency. */
    public function paidAmount(): float
    {
        return (float) ($this->cashLine()?->credit ?? 0);
    }

    public function bankAccount()
    {
        return $this->cashLine()?->account;
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

