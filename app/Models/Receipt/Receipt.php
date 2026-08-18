<?php

namespace App\Models\Receipt;

use App\Enums\PaymentMode;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Accounting\Settlement;
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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Receipt extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, HasDependencyCheck, HasSequentialNumber, SoftDeletes;
 
    protected $fillable = [
        'number',
        'date',
        'ledger_id', 
        'payment_mode',
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
        'payment_mode' => PaymentMode::class,
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
     * What this receipt settled, and at which rates.
     *
     * Reached through the voucher because settlement is a property of the
     * journal entry, not of the receipt document — which is what lets an
     * opening balance be settled by the same machinery as an invoice.
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
     * The line that carries the money actually received.
     *
     * Identified by the ACCOUNT TYPE, not by position or by "the first debit".
     * A settlement voucher now has several debits — a realised exchange loss is
     * one — and their order is whatever Postgres returns. Reading lines[0] gave
     * the edit form a receivable amount in place of the receipt amount.
     */
    public function cashLine()
    {
        return $this->transaction?->lines
            ?->first(fn ($line) => $line->account?->accountType?->slug === 'cash-or-bank');
    }

    /** What was received, in the voucher's own currency. */
    public function receivedAmount(): float
    {
        return (float) ($this->cashLine()?->debit ?? 0);
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
                'message' => 'This receipt has a linked transaction',
            ],
        ];
    }
}

