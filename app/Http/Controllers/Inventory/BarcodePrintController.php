<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;

class BarcodePrintController extends Controller
{
    public function __invoke()
    {
        $this->authorize('viewAny', Item::class);

        return inertia('Inventories/Items/BarcodePrint');
    }
}
