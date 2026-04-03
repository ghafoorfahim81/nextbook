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
use App\Traits\HasDynamicFilters;
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
use App\Models\Transaction\TransactionLine;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Ledger extends Model
{
    use HasFactory, HasUlids, HasCache, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    // ... your existing code ...

    /**
     * Statement accessor
     */
    protected function statement(): Attribute
    {
        return Attribute::make(
            get: function () {

                // Calculate total debit and credit, multiplying each by its transaction's rate
                $totals = TransactionLine::whereHas('transaction', function ($query) {
                        $query->where('ledger_id', $this->id);
                        //->where('status', 'posted');
                    })
                    ->join('transactions', 'transaction_lines.transaction_id', '=', 'transactions.id')
                    ->selectRaw('
                        SUM(transaction_lines.debit * transactions.rate) as total_debit,
                        SUM(transaction_lines.credit * transactions.rate) as total_credit
                    ')
                    ->first();

                $totalDebit  = (float) ($totals->total_debit ?? 0);
                $totalCredit = (float) ($totals->total_credit ?? 0);

                $netBalance = $totalDebit - $totalCredit;

                $balanceAmount = abs($netBalance);
                $balanceNature = $netBalance >= 0 ? 'dr' : 'cr';

                $natureFormat = balanceNatureFormat();

                $isSupplier = $this->type === 'supplier';
                // Format balance based on user preference
                if ($natureFormat === 'with_nature') {
                    $balance = $balanceAmount . ' ' . $balanceNature;
                } else {
                    if($isSupplier) {
                        $balance = $balanceNature === 'cr'
                            ? __('general.owe_to') . ' ' . $balanceAmount
                            : __('general.owe_you') . ' ' . $balanceAmount;
                    } else {
                        $balance = $netBalance >= 0
                            ? __('general.owe_you') . ' ' . $balanceAmount
                            : __('general.owe_to') . ' ' . $balanceAmount;
                    }
                }

                $normalNature = $this->type === 'supplier' ? 'cr' : 'dr';
                return [
                    'balance' => $balanceAmount>0 ? $balance : 0,
                    'balance_amount' => $balanceAmount,
                    'balance_nature' => $balanceNature,
                    'normal_balance_nature' => $normalNature,
                    'is_normal_balance' => $balanceNature === $normalNature,

                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'net_balance' => $netBalance,
                    'meaning' => $isSupplier
                        ? ($balanceNature === 'cr'
                            ? "You owe {$balanceAmount} to this supplier"
                            : "Supplier owes you {$balanceAmount}")
                        : ($balanceNature === 'dr'
                            ? "Customer owes you {$balanceAmount}"
                            : "You owe {$balanceAmount} to this customer"),
                    'account_type' => $this->type,

                    'payable_amount' => $balanceNature === 'cr' ? $balanceAmount : 0,
                    'receivable_amount' => $balanceNature === 'dr' ? $balanceAmount : 0,
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
        'is_main',
        'type',
        'is_active',
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
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'type' => LedgerType::class,
        ];
    }

    protected array $allowedFilters = [
        'name',
        'code',
        'currency_id',
        'created_by',
    ];

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'contact_person', 'phone_no', 'email'];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function opening()
    {
        return $this->morphOne(LedgerOpening::class, 'ledgerable');
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

    // public function ledgerTransactions()
    // {
    //     return $this->hasMany(LedgerTransaction::class);
    // }
    public function transactionLines(): HasMany
    {
        return $this->hasMany(TransactionLine::class, 'ledger_id', 'id');
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
