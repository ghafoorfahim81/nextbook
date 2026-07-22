<?php

namespace App\Models\Purchase;

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

class PurchaseReturnItem extends Model
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
        'purchase_return_id',
        'purchase_item_id',
        'item_id',
        'batch',
        'color',
        'expire_date',
        'quantity',
        'unit_measure_id',
        'unit_price',
        'size_id',
        'warehouse_id',
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
            'purchase_return_id' => 'string',
            'purchase_item_id' => 'string',
            'item_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_measure_id' => 'string',
            'unit_price' => 'decimal:4',
            'warehouse_id' => 'string',
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
            'purchaseReturn.number',
            'item.name',
            'item.code',
            'unitMeasure.name',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class);
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'warehouse_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Size::class);
    }
}
