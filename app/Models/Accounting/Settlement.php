<?php

namespace App\Models\Accounting;

use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One application of a receipt/payment line against one receivable/payable line.
 *
 * The pair of rates is the point of this record. `target_rate` is the rate the
 * claim was booked at and never changes; `settlement_rate` is the rate the cash
 * moved at. Their difference, valued on amount_applied, is the realised FX
 * result — and it is stored rather than recomputed, because the lines it was
 * derived from can be reversed or superseded later.
 *
 * Both receivables and payables live here. An AP settlement points
 * target_line_id at a credit line; nothing else differs.
 */
class Settlement extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $table = 'settlements';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'transaction_id',
        'settling_line_id',
        'target_line_id',
        'ledger_id',
        'currency_id',
        'amount_applied',
        'target_rate',
        'settlement_rate',
        'base_relieved',
        'forex_amount',
        'is_cross_currency',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'transaction_id' => 'string',
            'settling_line_id' => 'string',
            'target_line_id' => 'string',
            'ledger_id' => 'string',
            'currency_id' => 'string',
            'amount_applied' => 'float',
            'target_rate' => 'float',
            'settlement_rate' => 'float',
            'base_relieved' => 'float',
            'forex_amount' => 'float',
            'is_cross_currency' => 'boolean',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'deleted_by' => 'string',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** The receipt's AR credit line, or the payment's AP debit line. */
    public function settlingLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class, 'settling_line_id');
    }

    /** The invoice / opening / note line being relieved. */
    public function targetLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class, 'target_line_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** Realised loss. Stored negative, surfaced positive for display. */
    public function isLoss(): bool
    {
        return $this->forex_amount < 0;
    }
}
