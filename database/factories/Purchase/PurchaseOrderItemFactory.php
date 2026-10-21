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
            // Read off the purchase order: a line has no branch of its own, and its
            // company has to be the one its document is filed in. Declared
            // before the item because Laravel expands a definition in order and
            // the item is built in this company.
            'company_id' => fn (array $attributes) => PurchaseOrder::withoutGlobalScopes()
                ->whereKey($attributes['purchase_order_id'])
                ->value('company_id'),
            // A plain `Item::factory()` would fall back to a company of its own,
            // so the line would order something out of another company's
            // catalogue.
            'item_id' => fn (array $attributes) => Item::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'quantity' => fake()->randomFloat(2, 1, 5),
            'free' => 0,
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'unit_measure_id' => fn (array $attributes) => UnitMeasure::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            'batch' => null,
            'expire_date' => null,
            'category_id' => null,
            'discount' => 0,
        ];
    }
}
