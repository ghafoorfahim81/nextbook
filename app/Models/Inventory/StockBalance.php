<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Models\Administration\Branch;
class StockBalance extends Model
{
    use HasUlids, SoftDeletes, HasUserAuditable, BranchSpecific, HasBranch;
    protected $table = 'stock_balances';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $fillable = [
        'item_id',
        'branch_id',
        'quantity',
        'average_cost',
        'warehouse_id',
        'batch',
        'expire_date',
        'created_by',
        'updated_by',
    ];
    protected $casts = [
        'id' => 'string',
        'item_id' => 'string',
        'branch_id' => 'string',
        'quantity' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'warehouse_id' => 'string',
        'batch' => 'string',
        'expire_date' => 'date',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
