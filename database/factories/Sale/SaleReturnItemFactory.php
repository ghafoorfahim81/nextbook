<?php

namespace Database\Factories\Sale;

use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Sale\SaleItem;
use App\Models\Sale\SaleReturn;
use App\Models\Sale\SaleReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleReturnItemFactory extends Factory
{
    protected $model = SaleReturnItem::class;

    public function definition(): array
    {
        return [
            'sale_return_id' => SaleReturn::factory(),
            // Read off the sale return: a line has no branch of its own, and its
            // company has to be the one its document is filed in. Declared
            // before the two rows below, which are built in this company.
            'company_id' => fn (array $attributes) => SaleReturn::withoutGlobalScopes()
                ->whereKey($attributes['sale_return_id'])
                ->value('company_id'),
            // The line of the sale this return is against, so the returned line
            // and the sold line are in the same books.
            'sale_item_id' => fn (array $attributes) => SaleItem::factory()
                ->create([
                    'sale_id' => SaleReturn::withoutGlobalScopes()
                        ->whereKey($attributes['sale_return_id'])
                        ->value('sale_id'),
                ])
                ->getKey(),
            // Read off that line rather than built fresh: returning an item the
            // sale never contained — and from another company's catalogue, which
            // is what a plain `Item::factory()` gave — is not a state the
            // application can produce.
            'item_id' => fn (array $attributes) => SaleItem::withoutGlobalScopes()
                ->whereKey($attributes['sale_item_id'])
                ->value('item_id'),
            'batch' => null,
            'expire_date' => null,
            'quantity' => fake()->randomFloat(2, 1, 5),
            'unit_measure_id' => fn (array $attributes) => UnitMeasure::factory()
                ->create(['company_id' => $attributes['company_id']])
                ->getKey(),
            // In the return's own branch, which puts it in its company too. A
            // plain `Warehouse::factory()` builds its own branch, so the line
            // was returned into a warehouse in another company.
            'warehouse_id' => fn (array $attributes) => Warehouse::factory()
                ->create([
                    'branch_id' => SaleReturn::withoutGlobalScopes()
                        ->whereKey($attributes['sale_return_id'])
                        ->value('branch_id'),
                ])
                ->getKey(),
            'unit_price' => fake()->randomFloat(4, 10, 500),
            'net_unit_cost' => fake()->randomFloat(4, 5, 300),
        ];
    }
}
