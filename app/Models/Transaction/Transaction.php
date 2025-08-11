<?php

namespace App\Models\Transaction;

use App\Models\Account\Account;
use App\Models\LedgerOpening\LedgerOpening;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory, HasSearch, HasSorting, HasUserAuditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $keyType = 'string';
    public $incrementing = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new \Symfony\Component\Uid\Ulid();
        });
    }
    protected $fillable = [
        'account_id',
        'amount',
        'currency_id',
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
        return $this->hasOne(LedgerOpening::class,'transaction_id');
    }



}
