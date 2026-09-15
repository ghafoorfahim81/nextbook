<?php

namespace App\Models\Inventory;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustmentItem extends Model
{
    use HasFactory, HasUlids, HasUserTracking, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $fillable = [
        'stock_adjustment_id',
        'item_id',
        'variant_id',
        'unit_measure_id',
        'quantity',
        'unit_cost',
        'batch',
        'expire_date',
        'category_id',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'stock_adjustment_id' => 'string',
            'item_id' => 'string',
            'variant_id' => 'string',
            'unit_measure_id' => 'string',
            'quantity' => 'float',
            'unit_cost' => 'float',
            'expire_date' => 'date',
            'category_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** Which sellable variant this line moved — every item has at least one. */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'variant_id');
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Category::class);
    }
}
