<?php

namespace App\Models\Inventory;

use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasUlids;
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

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function opening()
    {
        return $this->belongsTo(StockOpening::class);
    }
}
