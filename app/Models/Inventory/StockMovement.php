<?php

namespace App\Models\Inventory;

use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
class StockMovement extends Model
{
    use HasUlids, SoftDeletes, HasUserAuditable, BranchSpecific, HasBranch,BranchSpecific;
    protected $table = 'stock_movements';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = [
        'id' => 'string',
        'branch_id' => 'string',
        'item_id' => 'string',
        'warehouse_id' => 'string',
        'unit_measure_id' => 'string',
        'size_id' => 'string',
        'movement_type' => StockMovementType::class,
        'source' => StockSourceType::class,
        'reference_id' => 'string',
        'reference_type' => 'string',
        'created_by' => 'string',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'qty_remaining' => 'decimal:4',
        'batch' => 'string',
        'expire_date' => 'date',
        'date' => 'date',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];
    protected $fillable = [
        'id',
        'branch_id',
        'item_id',
        'warehouse_id',
        'unit_measure_id',
        'size_id',
        'movement_type',
        'source',
        'reference_id',
        'reference_type',
        'quantity',
        'unit_cost',
        'qty_remaining',
        'batch',
        'expire_date',
        'date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class);
    }

    public function unit_measure()
    {
        return $this->belongsTo(UnitMeasure::class);
    }

    public function unitMeasure()
    {
        return $this->belongsTo(UnitMeasure::class, 'unit_measure_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function opening()
    {
        return $this->hasOne(StockOpening::class, 'stock_id'); // StockOpening has stock_id
    }

    public function source()
    {
        return $this->morphTo();
    }

    public function ledgerName()
    {
        if($this->source_type == 'App\Models\Purchase\Purchase' && $this->source) {
            return $this->source->supplier->name ?? 'Unknown';
        }
        else{
            return 'Unknown';
        }
    }
}
