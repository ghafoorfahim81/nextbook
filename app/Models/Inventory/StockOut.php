<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Store;
use App\Models\Inventory\Item;
use App\Models\Inventory\Stock;
class StockOut extends Model
{
        use HasUlids, SoftDeletes;

    protected $table = 'stock_outs';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];
    protected $casts = [
        'id' => 'string',
        'stock_id' => 'string',
        'item_id' => 'string',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'free' => 'decimal:2',
        'tax' => 'decimal:2',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function unit_measure()
    {
        return $this->belongsTo(UnitMeasure::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function source()
    {
        return $this->morphTo();
    }
}
