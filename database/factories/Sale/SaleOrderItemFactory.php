<?php

namespace Database\Factories\Sale;

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
            // Read off the sale order: a line has no branch of its own, and its
            // company has to be the one its document is filed in.
            'company_id' => fn (array $attributes) => SaleOrder::withoutGlobalScopes()
                ->whereKey($attributes['sale_order_id'])
                ->value('company_id'),
        ];
    }
}
