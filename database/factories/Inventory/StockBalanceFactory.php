<?php

namespace Database\Factories\Inventory;

use App\Enums\StockStatus;
use App\Models\Administration\Branch;
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
            'item_id' => Item::factory(),
            'warehouse_id' => Warehouse::factory(),
            'batch' => null,
            'expire_date' => null,
            'status' => StockStatus::DRAFT->value,
            'quantity' => fake()->randomFloat(4, 1, 100),
            'average_cost' => fake()->randomFloat(4, 1, 500),
            'branch_id' => Branch::factory(),
            // Read off the branch rather than built separately: a document's
            // company and its branch's company have to agree, and the composite
            // foreign key enforces that.
            'company_id' => fn (array $attributes) => Branch::withoutGlobalScopes()
                ->whereKey($attributes['branch_id'])
                ->value('company_id'),
        ];
    }
}
