<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Inventory\Item;

class BarcodePrintController extends Controller
{
    public function __invoke()
    {
        $this->authorize('viewAny', Item::class);

        return inertia('Inventories/Items/BarcodePrint', [
            // The picker used to open empty, so labelling a product you just
            // created meant typing its name first. The newest items are almost
            // always the ones needing labels, so they seed the list.
            'recentItems' => ItemResource::collection(
                Item::query()
                    ->with(['unitMeasure', 'variants'])
                    // created_at alone ties for everything made in the same
                    // second — a bulk fast entry, an import — and a tie leaves
                    // the order up to the database. ULIDs sort by creation time,
                    // so they settle it.
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            ),
        ]);
    }
}
