<?php

namespace Database\Factories\Inventory;

use App\Models\Administration\Company;
use App\Enums\ItemType;
use App\Models\Account\Account;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Models\Administration\Brand;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;

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
            // Declared first on purpose. Laravel expands a definition in order
            // and hands each closure the attributes resolved before it, so the
            // three posting accounts below can only inherit this company if it
            // is already known by the time they are built — and that includes
            // the value a caller passed in, which is merged in before
            // expansion.
            //
            // They used to be plain `Account::factory()` calls, which fall back
            // to a company of their own: an item in one company pointing at
            // three accounts in three others. Nothing noticed until the row
            // level security policies refused the insert.
            'company_id' => Company::factory(),
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
            'cost_account_id' => fn (array $attributes) => $this->accountIn($attributes['company_id']),
            'income_account_id' => fn (array $attributes) => $this->accountIn($attributes['company_id']),
            'asset_account_id' => fn (array $attributes) => $this->accountIn($attributes['company_id']),
            'minimum_stock' => fake()->randomFloat(2, 1, 20),
            'maximum_stock' => fake()->randomFloat(2, 21, 200),
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
        ];
    }

    /**
     * A posting account in the item's own company.
     *
     * Returns the key rather than a factory instance: handing back a factory
     * would let it resolve a company of its own, which is the bug this replaces.
     */
    private function accountIn(string $companyId): string
    {
        return Account::factory()->create(['company_id' => $companyId])->getKey();
    }
}
