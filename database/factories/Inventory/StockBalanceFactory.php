<?php

namespace Database\Factories\Inventory;

use App\Enums\StockStatus;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            // Read off the warehouse: stock has no branch of its own, and its
            // company is the one that owns the warehouse. Declared before the
            // item because Laravel expands a definition in order and the item is
            // built in this company.
            'company_id' => fn (array $attributes) => Warehouse::withoutGlobalScopes()
                ->whereKey($attributes['warehouse_id'])
                ->value('company_id'),
            // A plain `Item::factory()` would fall back to a company of its own,
            // so the balance would count stock of an item belonging to someone
            // else.
            'item_id' => fn (array $attributes) => Item::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'batch' => null,
            'expire_date' => null,
            'status' => StockStatus::DRAFT->value,
            'quantity' => fake()->randomFloat(4, 1, 100),
            'average_cost' => fake()->randomFloat(4, 1, 500),
        ];
    }
}
