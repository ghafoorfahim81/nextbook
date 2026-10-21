<?php

namespace Database\Factories\Sale;

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
            // Read off the sale: a line has no branch of its own, and its
            // company has to be the one its document is filed in. Declared
            // before the item because Laravel expands a definition in order and
            // the item is built in this company.
            'company_id' => fn (array $attributes) => Sale::withoutGlobalScopes()
                ->whereKey($attributes['sale_id'])
                ->value('company_id'),
            // A plain `Item::factory()` would fall back to a company of its own,
            // so the line would sell something out of another company's
            // catalogue.
            'item_id' => fn (array $attributes) => Item::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'batch' => fake()->optional()->bothify('BATCH-###'),
            'expire_date' => null,
            'quantity' => fake()->randomFloat(2, 1, 50),
            'unit_measure_id' => fn (array $attributes) => UnitMeasure::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            // In the sale's own branch, which puts it in the sale's company too.
            // A plain `Warehouse::factory()` builds its own branch, so the line
            // shipped from a warehouse in another company.
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()
                ->create([
                    'branch_id' => Sale::withoutGlobalScopes()
                        ->whereKey($attributes['sale_id'])
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
