<?php

namespace Database\Factories\Sale;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'item_id' => Item::factory(),
            'batch' => fake()->optional()->bothify('BATCH-###'),
            'expire_date' => null,
            'quantity' => fake()->randomFloat(2, 1, 50),
            'unit_measure_id' => UnitMeasure::factory(),
            'warehouse_id' => Warehouse::factory(),
            'size_id' => null,
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'net_unit_cost' => null,
            'discount' => fake()->randomFloat(2, 0, 10),
            'free' => fake()->randomFloat(2, 0, 5),
            'tax' => fake()->randomFloat(2, 0, 5),
            'branch_id' => Branch::factory(),
        ];
    }
}
