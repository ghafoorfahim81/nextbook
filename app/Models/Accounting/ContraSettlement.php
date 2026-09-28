<?php

namespace App\Models\Accounting;

use App\Models\Administration\Currency;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A set-off between the two ledgers of one party.
 *
 * The document records the agreement; the accounting lives in the two
 * settlement vouchers it points at. Keeping their ids here rather than
 * discovering them by reference is deliberate — both vouchers carry this
 * document as their reference, so only the ids say which is which.
 *
 * @see \App\Services\Accounting\ContraSettlementService
 */
class ContraSettlement extends Model
{
    use BranchSpecific;
    use HasBranch;
    use HasDynamicFilters;
    use HasFactory;
    use HasSearch;
    use HasSequentialNumber;
    use HasSorting;
    use HasUlids;
    use HasUserAuditable;
    use HasUserTracking;
    use SoftDeletes;

    protected $fillable = [
        'number',
        'date',
        'customer_ledger_id',
        'supplier_ledger_id',
        'currency_id',
        'rate',
        'amount',
        'narration',
        'status',
        'customer_transaction_id',
        'supplier_transaction_id',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'customer_ledger_id' => 'string',
        'supplier_ledger_id' => 'string',
        'currency_id' => 'string',
        'rate' => 'float',
        'amount' => 'float',
        'date' => 'date',
        'status' => 'string',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'number',
            'date',
            'narration',
            'customerLedger.name',
            'supplierLedger.name',
        ];
    }

    protected array $allowedFilters = [
        'customer_ledger_id',
        'supplier_ledger_id',
        'currency_id',
        'status',
        'date',
        'created_by',
    ];

    public function customerLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'customer_ledger_id');
    }

    public function supplierLedger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'supplier_ledger_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function customerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'customer_transaction_id');
    }

    public function supplierTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'supplier_transaction_id');
    }

    /** @return array<int, string> the two voucher ids, skipping any not posted */
    public function transactionIds(): array
    {
        return array_values(array_filter([
            $this->customer_transaction_id,
            $this->supplier_transaction_id,
        ]));
    }
}
