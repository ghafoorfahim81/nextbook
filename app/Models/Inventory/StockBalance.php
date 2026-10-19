<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
use App\Traits\BranchSpecificThroughWarehouse;
use App\Traits\CompanySpecific;
use App\Traits\HasCompanyFromParent;
use App\Enums\StockStatus;
class StockBalance extends Model
{
    use HasUlids, SoftDeletes, HasUserAuditable, CompanySpecific, BranchSpecificThroughWarehouse, HasCompanyFromParent;
    protected $table = 'stock_balances';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = [
        'company_id',
        'item_id',
        'variant_id',
        'lot_id',
        'quantity',
        'reserved_out',
        'reserved_in',
        'warehouse_id',
        'status',
        'batch',
        'expire_date',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'id' => 'string',
        'item_id' => 'string',
        'quantity' => 'decimal:4',
        'reserved_out' => 'decimal:4',
        'reserved_in' => 'decimal:4',
        'warehouse_id' => 'string',
        'batch' => 'string',
        'expire_date' => 'date',
        'status' => StockStatus::class,
        'created_by' => 'string',
        'updated_by' => 'string',
    ];
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function variant()
    {
        return $this->belongsTo(ItemVariant::class, 'variant_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'warehouse_id');
    }

    /** No branch of its own: the warehouse holds it, and the company comes from there too. */
    protected function companyParentRelation(): string
    {
        return 'warehouse';
    }
}
