<?php

namespace Database\Factories\Purchase;

use App\Models\Administration\Branch;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Purchase\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseReturnItemFactory extends Factory
{
    protected $model = PurchaseReturnItem::class;

    public function definition(): array
    {
        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            'purchase_item_id' => PurchaseItem::factory(),
            'item_id' => Item::factory(),
            'batch' => null,
            'expire_date' => null,
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit_measure_id' => UnitMeasure::factory(),
            'warehouse_id' => Warehouse::factory(),
            'size_id' => null,
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'branch_id' => Branch::factory(),
        ];
    }
}
