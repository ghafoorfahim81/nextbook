<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Models\Administration\Brand;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\User;

class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Item::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => fake()->word(),
            'generic_name' => fake()->word(),
            'packing' => fake()->word(),
            'barcode' => fake()->word(),
            'unit_measure_id' => UnitMeasure::factory(),
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'minimum_stock' => fake()->randomFloat(0, 0, 9999999999.),
            'maximum_stock' => fake()->randomFloat(0, 0, 9999999999.),
            'colors' => fake()->word(),
            'size' => fake()->word(),
            'photo' => fake()->word(),
            'purchase_price' => fake()->randomFloat(0, 0, 9999999999.),
            'cost' => fake()->randomFloat(0, 0, 9999999999.),
            'mrp_rate' => fake()->randomFloat(0, 0, 9999999999.),
            'rate_a' => fake()->randomFloat(0, 0, 9999999999.),
            'rate_b' => fake()->randomFloat(0, 0, 9999999999.),
            'rate_c' => fake()->randomFloat(0, 0, 9999999999.),
            'rack_no' => fake()->numberBetween(-10000, 10000),
            'fast_search' => fake()->word(),
            'branch_id' => Branch::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }
}
