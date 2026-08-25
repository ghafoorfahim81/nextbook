<?php

namespace App\Models\Inventory;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandedCostItem extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $table = 'landed_cost_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'landed_cost_id',
        'purchase_item_id',
        'item_id',
        'quantity',
        'unit_cost',
        'weight',
        'volume',
        'warehouse_id',
        'batch',
        'expire_date',
        'allocated_percentage',
        'allocated_amount',
        'item_cost_before',
        'item_cost_after',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'landed_cost_id' => 'string',
            'purchase_item_id' => 'string',
            'item_id' => 'string',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'weight' => 'decimal:4',
            'volume' => 'decimal:4',
            'warehouse_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'allocated_percentage' => 'decimal:4',
            'allocated_amount' => 'decimal:2',
            'item_cost_before' => 'decimal:2',
            'item_cost_after' => 'decimal:2',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'deleted_by' => 'string',
        ];
    }

    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class, 'landed_cost_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Purchase\PurchaseItem::class, 'purchase_item_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
