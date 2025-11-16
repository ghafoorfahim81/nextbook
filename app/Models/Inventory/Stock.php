<?php

namespace App\Models\Inventory;

use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Inventory\StockOut;
class Stock extends Model
{
    use HasUlids, SoftDeletes;
    protected $table = 'stocks';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

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

    public function stockOuts()
    {
        return $this->hasMany(StockOut::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
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
