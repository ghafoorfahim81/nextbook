<?php

namespace App\Models\Ledger;

use App\Models\Administration\Branch;
use App\Models\Ledger\LedgerOpening;
use App\Models\Sale\Sale;
use App\Models\Receipt\Receipt; 
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
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\HasCache;
use App\Models\Purchase\Purchase;
use App\Models\Payment\Payment;
use App\Enums\LedgerType;
class Ledger extends Model
{
    use HasFactory, HasUlids, HasCache, HasSearch, HasSorting, HasUserAuditable, HasBranch, HasDependencyCheck, SoftDeletes;

    // ... your existing code ...

    /**
     * Statement accessor
     */
    protected function statement(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Use the new ledger_transactions pivot table to get all related transactions
                // Assume you have a 'transactions' relationship through the pivot table (LedgerTransaction model)
                $transactions = $this->ledgerTransactions()->get();

                // Calculate totals
                $totals = $transactions->reduce(function ($carry, $transaction) {
                    $amount = $transaction->transaction->amount * $transaction->transaction->rate;
                    $carry[$transaction->transaction->type] += $amount;
                    return $carry;
                }, ['debit' => 0, 'credit' => 0]);

                $netBalance = $totals['debit'] - $totals['credit'];
                $balanceAmount = abs($netBalance);
                $balanceNature = $netBalance >= 0 ? 'dr' : 'cr';
                $isSupplier = $this->type === 'supplier';

                return [
                    'balance' => $balanceAmount,
                    'balance_nature' => $balanceNature,
                    'normal_balance_nature' => $isSupplier ? 'cr' : 'dr',
                    'is_normal_balance' => $balanceNature === ($isSupplier ? 'cr' : 'dr'),
                    'total_debit' => $totals['debit'],
                    'total_credit' => $totals['credit'],
                    'net_balance' => $netBalance,
                    'account_type' => $this->type,
                    'payable_amount' => $balanceNature === 'cr' ? $balanceAmount : 0,
                    'receivable_amount' => $balanceNature === 'dr' ? $balanceAmount : 0,
                    'meaning' => $isSupplier
                        ? ($balanceNature === 'cr'
                            ? "You owe {$balanceAmount} to this supplier"
                            : "Supplier owes you {$balanceAmount}")
                        : ($balanceNature === 'dr'
                            ? "Customer owes you {$balanceAmount}"
                            : "You owe {$balanceAmount} to this customer"),
                ];
            }
        );
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'contact_person',
        'phone_no',
        'branch_id',
        'email',
        'currency_id',
        'type',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'branch_id' => 'string',
            'type' => LedgerType::class,
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function openings()
    {
        return $this->morphMany(LedgerOpening::class, 'ledgerable');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id', 'id');
    }

    public function receipts()
    {
        return $this->hasMany(Receipt::class, 'ledger_id', 'id');
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'supplier_id', 'id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'ledger_id', 'id');
    }

    public function ledgerTransactions()
    {
        return $this->hasMany(LedgerTransaction::class);
    }
    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'transactions' => [
                'model' => 'transactions',
                'message' => 'This ledger has transactions'
            ]
        ];
    }
}
