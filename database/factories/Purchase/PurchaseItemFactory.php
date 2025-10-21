<?php

namespace Database\Factories\Purchase;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Purchase\Purchase;
use App\Models\Inventory\Item;
use App\Models\Administration\UnitMeasure;
use App\Models\User;

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
            'batch' => fake()->word(),
            'expire_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'quantity' => fake()->randomFloat(2, 1, 1000),
            'unit_measure_id' => UnitMeasure::factory(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'discount' => fake()->randomFloat(2, 0, 100),
            'free' => fake()->randomFloat(2, 0, 50),
            'tax' => fake()->randomFloat(2, 0, 50),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
