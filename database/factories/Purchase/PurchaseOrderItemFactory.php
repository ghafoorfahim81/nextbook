<?php

namespace Database\Factories\Purchase;

use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Purchase\PurchaseOrder;
use App\Models\Purchase\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'item_id' => Item::factory(),
            'quantity' => fake()->randomFloat(2, 1, 5),
            'free' => 0,
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'unit_measure_id' => UnitMeasure::factory(),
            'batch' => null,
            'expire_date' => null,
            'category_id' => null,
            'discount' => 0,
            // Read off the purchase order: a line has no branch of its own, and its
            // company has to be the one its document is filed in.
            'company_id' => fn (array $attributes) => PurchaseOrder::withoutGlobalScopes()
                ->whereKey($attributes['purchase_order_id'])
                ->value('company_id'),
        ];
    }
}
