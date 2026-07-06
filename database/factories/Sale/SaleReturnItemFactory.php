<?php

namespace Database\Factories\Sale;

use App\Models\Administration\Branch;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleReturn;
use App\Models\Sale\SaleReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleReturnItemFactory extends Factory
{
    protected $model = SaleReturnItem::class;

    public function definition(): array
    {
        return [
            'sale_return_id' => SaleReturn::factory(),
            'sale_item_id' => SaleItem::factory(),
            'item_id' => Item::factory(),
            'batch' => null,
            'expire_date' => null,
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit_measure_id' => UnitMeasure::factory(),
            'warehouse_id' => Warehouse::factory(),
            'size_id' => null,
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'net_unit_cost' => fake()->randomFloat(4, 5, 300),
            'branch_id' => Branch::factory(),
        ];
    }
}
