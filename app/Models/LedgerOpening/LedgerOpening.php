<?php

namespace App\Models\LedgerOpening;

use App\Models\Transaction\Transaction;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerOpening extends Model
{
    use HasFactory,HasUlids, HasSearch, HasSorting, HasUserAuditable;


    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ledgerable_id',
        'ledgerable_type',
        'transaction_id',
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
            'ledgerable_id' => 'string',
            'ledgerable_type' => 'string',
            'transaction_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    public function transactionable(): BelongsTo
    {
        return $this->morphTo();
    }

    public function ledgerable(): BelongsTo
    {
        return $this->morphTo();
    }

    public function ledger()
    {
        return $this->morphTo();
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }


}
