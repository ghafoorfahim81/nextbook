<?php

namespace Database\Factories\Purchase;

use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Purchase\PurchaseItem;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Purchase\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseReturnItemFactory extends Factory
{
    protected $model = PurchaseReturnItem::class;

    public function definition(): array
    {
        return [
            'purchase_return_id' => PurchaseReturn::factory(),
            // Read off the purchase return: a line has no branch of its own, and its
            // company has to be the one its document is filed in. Declared
            // before the two rows below, which are built in this company.
            'company_id' => fn (array $attributes) => PurchaseReturn::withoutGlobalScopes()
                ->whereKey($attributes['purchase_return_id'])
                ->value('company_id'),
            // The line of the purchase this return is against, so the returned
            // line and the bought line are in the same books.
            'purchase_item_id' => fn (array $attributes) => PurchaseItem::factory()
                ->create([
                    'purchase_id' => PurchaseReturn::withoutGlobalScopes()
                        ->whereKey($attributes['purchase_return_id'])
                        ->value('purchase_id'),
                ])
                ->getKey(),
            // Read off that line rather than built fresh: returning an item the
            // purchase never contained — and from another company's catalogue,
            // which is what a plain `Item::factory()` gave — is not a state the
            // application can produce.
            'item_id' => fn (array $attributes) => PurchaseItem::withoutGlobalScopes()
                ->whereKey($attributes['purchase_item_id'])
                ->value('item_id'),
            'batch' => null,
            'expire_date' => null,
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit_measure_id' => fn (array $attributes) => UnitMeasure::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            // In the return's own branch, which puts it in its company too. A
            // plain `Warehouse::factory()` builds its own branch, so the line
            // was returned out of a warehouse in another company.
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()
                ->create([
                    'branch_id' => PurchaseReturn::withoutGlobalScopes()
                        ->whereKey($attributes['purchase_return_id'])
                        ->value('branch_id'),
                ])
                ->getKey(),
            'unit_price' => fake()->randomFloat(4, 10, 500),
        ];
    }
}
