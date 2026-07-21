<?php

namespace App\Models\Sale;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;

class SaleOrderItem extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasDependencyCheck, SoftDeletes, BranchSpecific, HasBranch;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sale_order_id',
        'item_id',
        'quantity',
        'free',
        'unit_price',
        'unit_measure_id',
        'batch',
        'color',
        'expire_date',
        'size_id',
        'category_id',
        'discount',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_order_id' => 'string',
            'item_id' => 'string',
            'quantity' => 'decimal:2',
            'free' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'unit_measure_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'size_id' => 'string',
            'category_id' => 'string',
            'discount' => 'decimal:2',
            'created_by' => 'string',
            'updated_by' => 'string',
            'branch_id' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'batch',
            'expire_date',
            'quantity',
            'unit_price',
            'discount',
            'saleOrder.number',
            'item.name',
            'item.code',
            'unitMeasure.name',
        ];
    }

    public function saleOrder(): BelongsTo
    {
        return $this->belongsTo(SaleOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class);
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Size::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Category::class);
    }
}
