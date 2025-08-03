<?php

namespace App\Models\LedgerOpening;

use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerOpening extends Model
{
    use HasFactory, HasSearch, HasSorting, HasUserAuditable;


    protected $keyType = 'string';
    public $incrementing = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new \Symfony\Component\Uid\Ulid();
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transactionable',
        'ledgerable',
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
        return $this->morphTo();
    }

}
