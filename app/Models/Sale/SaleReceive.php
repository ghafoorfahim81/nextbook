<?php

namespace App\Models\Sale;

use App\Models\Receipt\Receipt;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReceive extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'receipt_id',
        'amount',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'sale_id' => 'string',
            'receipt_id' => 'string',
            'amount' => 'float',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'deleted_by' => 'string',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
