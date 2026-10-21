<?php

namespace Database\Factories\Purchase;

use Illuminate\Database\Eloquent\Factories\Factory;
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
            // Read off the purchase: a line has no branch of its own, and its
            // company has to be the one its document is filed in. Declared
            // before the item because Laravel expands a definition in order and
            // the item is built in this company.
            'company_id' => fn (array $attributes) => Purchase::withoutGlobalScopes()
                ->whereKey($attributes['purchase_id'])
                ->value('company_id'),
            // A plain `Item::factory()` would fall back to a company of its own,
            // so the line would buy something into another company's catalogue.
            'item_id' => fn (array $attributes) => Item::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'batch' => fake()->optional()->bothify('BATCH-###'),
            'expire_date' => fake()->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit_measure_id' => fn (array $attributes) => UnitMeasure::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            // In the purchase's own branch, which puts it in the purchase's
            // company too. A plain `Warehouse::factory()` builds its own branch,
            // so the line was received into a warehouse in another company.
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()
                ->create([
                    'branch_id' => Purchase::withoutGlobalScopes()
                        ->whereKey($attributes['purchase_id'])
                        ->value('branch_id'),
                ])
                ->getKey(),
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'net_unit_cost' => null,
            'discount' => fake()->randomFloat(2, 0, 10),
            'free' => fake()->randomFloat(2, 0, 5),
            'tax' => fake()->randomFloat(2, 0, 5),
        ];
    }
}
