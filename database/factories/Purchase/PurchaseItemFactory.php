<?php

namespace Database\Factories\Purchase;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use App\Models\Purchase\Purchase;
use App\Models\Inventory\Item;
use App\Models\Administration\UnitMeasure;

class PurchaseItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \App\Models\Purchase\PurchaseItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'item_id' => Item::factory(),
            'batch' => fake()->optional()->bothify('BATCH-###'),
            'expire_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit_measure_id' => UnitMeasure::factory(),
            'warehouse_id' => Warehouse::factory(),
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'net_unit_cost' => null,
            'discount' => fake()->randomFloat(2, 0, 10),
            'free' => fake()->randomFloat(2, 0, 5),
            'tax' => fake()->randomFloat(2, 0, 5),
            'branch_id' => Branch::factory(),
        ];
    }
}
