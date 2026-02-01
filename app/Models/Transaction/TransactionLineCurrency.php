<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Transaction\TransactionLine;
use App\Models\Administration\Currency;
class TransactionLineCurrency extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'transaction_line_currencies';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'transaction_line_id',
        'currency_id',
        'exchange_rate',
        'deleted_by',
        'branch_id',
    ];

    protected $casts = [
        'id' => 'string',
        'transaction_line_id' => 'string',
        'currency_id' => 'string',
        'exchange_rate' => 'float',
        'deleted_by' => 'string',
    ];

    public function transactionLine(): BelongsTo
    {
        return $this->belongsTo(TransactionLine::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
