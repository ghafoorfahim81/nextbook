<?php

namespace App\Models\Transaction;

use App\Models\Account\Account;
use App\Models\Ledger\LedgerOpening;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\TransactionLine;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Transaction extends Model
{
    use HasFactory, HasSearch, HasSorting, HasUlids, HasUserAuditable, SoftDeletes, BranchSpecific, HasBranch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [ 
        'voucher_number',
        'status',
        'currency_id',
        'reference_type',
        'reference_id',
        'rate',
        'date',
        'remark',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'voucher_number' => 'string',
            'status' => 'string',
            'transactionable_type' => 'string',
            'transactionable_id' => 'string',
            'currency_id' => 'string',
            'rate' => 'float',
            'date' => 'date',
            'created_by' => 'string',
            'updated_by' => 'string',
            'branch_id' => 'string',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class, 'transaction_id');
    }

    public function opening()
    {
        return $this->hasOne(LedgerOpening::class, 'transaction_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    // Helper methods for common types
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Purchase\Purchase::class, 'reference_id')
            ->where('reference_type', 'purchase');
    }
    public function sale(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sale\Sale::class, 'reference_id')
            ->where('reference_type', 'sale');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Expense\Expense::class, 'reference_id')
            ->where('reference_type', 'expense');
    }

    public function income(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Sale\Sale::class, 'reference_id')
            ->where('reference_type', 'income');
    }
}
