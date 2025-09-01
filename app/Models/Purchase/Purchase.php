<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Purchase extends Model
{
    use HasFactory,HasUlids, HasSearch, HasSorting, HasUserAuditable, HasBranch;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'supplier_id',
        'date',
        'transaction_id',
        'discount',
        'discount_type',
        'type',
        'description',
        'status',
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
            'supplier_id' => 'integer',
            'date' => 'date',
            'transaction_id' => 'integer',
            'discount' => 'decimal',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'number', 
            'date', 
            'discount',
            'discount_type',
            'type',
            'description',
            'status',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ledger\Ledger::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Transaction\Transaction::class);
    }
}
