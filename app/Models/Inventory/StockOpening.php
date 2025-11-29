<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
class StockOpening extends Model
{
    use HasUlids, SoftDeletes, HasUserAuditable;
    protected $table = 'stock_openings';
    protected $primaryKey = 'id';
    public $incrementing = false; 
    protected $fillable = [
        'item_id',
        'stock_id',
    ];

    public function stock()
    {
        return $this->belongsTo(Stock::class);
    }
}
