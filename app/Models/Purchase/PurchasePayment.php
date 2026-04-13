<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific; 
class PurchasePayment extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'purchase_id',
        'payment_id',
        'amount',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_id' => 'string',
            'payment_id' => 'string',
            'amount' => 'float',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'deleted_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'purchase_id',
            'payment_id',
        ];
    }

    protected array $allowedFilters = [
        'purchase_id',
        'payment_id',
        'branch_id',
        'created_by',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Purchase\Purchase::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Payment\Payment::class);
    }

}
