<?php

namespace App\Models\Administration;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class CurrencyRateUpdate extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasSearch, BranchSpecific, HasBranch;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'currency_id',
        'exchange_rate',
        'date',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'currency_id' => 'string',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'date' => 'date',
        'exchange_rate' => 'decimal:8',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) new Ulid();
        });
    }

    protected static function searchableColumns(): array
    {
        return [
            'currency.name',
            'currency.code',
            'currency.symbol',
            'currency.format',
            'date',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
