<?php

namespace App\Models\Transaction;

use App\Models\Account\Account;
use App\Models\Ledger\LedgerOpening;
use App\Models\Ledger\Ledger;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class Transaction extends Model
{
    use HasFactory, HasSearch, HasSorting, HasUlids, HasUserAuditable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = [
        'account_id',
        'amount',
        'currency_id',
        'reference_type',
        'reference_id',
        'rate',
        'date',
        'type',
        'remark',
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
            'id' => 'string',
            'account_id' => 'string',
            'transactionable_type' => 'string',
            'transactionable_id' => 'string',
            'amount' => 'float',
            'currency_id' => 'string',
            'rate' => 'float',
            'date' => 'date',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
