<?php

namespace Database\Factories\Inventory;

use App\Enums\ItemType;
use App\Models\Account\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Models\Administration\Brand;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Administration\Size;

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
        $assetAccount = Account::factory();
        $incomeAccount = Account::factory();
        $costAccount = Account::factory();

        return [
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('ITM###')),
            'item_type' => ItemType::INVENTORY_MATERIALS->value,
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'generic_name' => fake()->optional()->word(),
            'packing' => fake()->optional()->word(),
            'barcode' => fake()->optional()->ean13(),
            'unit_measure_id' => UnitMeasure::factory(),
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'cost_account_id' => $costAccount,
            'income_account_id' => $incomeAccount,
            'asset_account_id' => $assetAccount,
            'minimum_stock' => fake()->randomFloat(2, 1, 20),
            'maximum_stock' => fake()->randomFloat(2, 21, 200),
            'colors' => [],
            'size_id' => Size::factory(),
            'margin_percentage' => fake()->randomFloat(2, 0, 50),
            'purchase_price' => fake()->randomFloat(2, 10, 500),
            'cost' => fake()->randomFloat(2, 10, 500),
            'sale_price' => fake()->randomFloat(2, 11, 700),
            'rate_a' => fake()->randomFloat(2, 11, 700),
            'rate_b' => fake()->randomFloat(2, 11, 700),
            'rate_c' => fake()->randomFloat(2, 11, 700),
            'rack_no' => (string) fake()->numberBetween(1, 100),
            'fast_search' => fake()->lexify('FAST-????'),
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'branch_id' => Branch::factory(),
        ];
    }
}
