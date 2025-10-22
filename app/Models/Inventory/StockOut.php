<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockOut extends Model
{
    public function source()
    {
        return $this->morphTo();
    }
}
