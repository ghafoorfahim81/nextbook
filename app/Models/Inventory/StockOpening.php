<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
class StockOpening extends Model
{
    use HasUlids, SoftDeletes, HasUserAuditable, BranchSpecific, HasBranch;
    protected $table = 'stock_openings';
    protected $primaryKey = 'id';
    public $incrementing = false; 
    protected $fillable = [
        'item_id',
        'branch_id',
        'stock_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'item_id' => 'string',
        'branch_id' => 'string',
        'stock_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];
    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
