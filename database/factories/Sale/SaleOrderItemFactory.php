<?php

namespace Database\Factories\Sale;

use App\Models\Administration\Branch;
use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Sale\SaleOrder;
use App\Models\Sale\SaleOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleOrderItemFactory extends Factory
{
    protected $model = SaleOrderItem::class;

    public function definition(): array
    {
        return [
            'sale_order_id' => SaleOrder::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->randomFloat(2, 1, 5),
            'free' => 0,
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'unit_measure_id' => UnitMeasure::factory(),
            'batch' => null,
            'expire_date' => null,
            'category_id' => null,
            'discount' => 0,
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
