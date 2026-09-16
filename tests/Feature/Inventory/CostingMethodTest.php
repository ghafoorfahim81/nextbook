<?php

namespace Tests\Feature\Inventory;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockMovement;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Does costing actually work?
 *
 * Two layers at different costs, then one issue, under each method. What the
 * stock ledger records and what the sale books as cost of goods have to agree,
 * and both have to follow the method the ITEM is on — an item may override the
 * company default.
 */
class CostingMethodTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        app(ItemVariantService::class)->ensureDefault($this->ctx['item']);
    }

    /** Two layers: 5 @ 10 on the 1st, then 5 @ 30 on the 2nd. Average is 20. */
    private function receiveTwoLayers(): void
    {
        foreach ([['2026-03-01', 10], ['2026-03-02', 30]] as [$date, $cost]) {
            app(StockService::class)->post([
                'item_id' => $this->ctx['item']->id,
                'movement_type' => StockMovementType::IN->value,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'quantity' => 5,
                'source' => StockSourceType::PURCHASE->value,
                'unit_cost' => $cost,
                'status' => StockStatus::POSTED->value,
                'batch' => null,
                'expire_date' => null,
                'date' => $date,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'branch_id' => $this->ctx['branch']->id,
                'reference_type' => 'opening',
                'reference_id' => $this->ctx['item']->id,
            ]);
        }
    }

    private function useCompanyMethod(CostingMethod $method): void
    {
        $this->ctx['company']->update(['costing_method' => $method->value]);
        \App\Support\BranchContext::flush();
    }

    private function sellTwo(): Sale
    {
        $this->post(route('sales.store'), [
            'number' => 4401,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => 200,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 2,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 100,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Sale::query()->latest()->firstOrFail();
    }

    private function outMovementCost(): float
    {
        return (float) StockMovement::query()
            ->where('movement_type', StockMovementType::OUT->value)
            ->latest('id')
            ->firstOrFail()
            ->unit_cost;
    }

    private function bookedUnitCost(): float
    {
        return (float) SaleItem::query()->latest('id')->firstOrFail()->net_unit_cost;
    }

    public function test_fifo_issues_at_the_oldest_layer_cost(): void
    {
        $this->useCompanyMethod(CostingMethod::FIFO);
        $this->receiveTwoLayers();

        $this->sellTwo();

        $this->assertEqualsWithDelta(10.0, $this->outMovementCost(), 0.0001, 'stock ledger');
        $this->assertEqualsWithDelta(10.0, $this->bookedUnitCost(), 0.0001, 'cost of goods sold');
    }

    public function test_lifo_issues_at_the_newest_layer_cost(): void
    {
        $this->useCompanyMethod(CostingMethod::LIFO);
        $this->receiveTwoLayers();

        $this->sellTwo();

        $this->assertEqualsWithDelta(30.0, $this->outMovementCost(), 0.0001, 'stock ledger');
        $this->assertEqualsWithDelta(30.0, $this->bookedUnitCost(), 0.0001, 'cost of goods sold');
    }

    public function test_weighted_average_issues_at_the_blended_cost(): void
    {
        $this->useCompanyMethod(CostingMethod::WEIGHTED_AVERAGE);
        $this->receiveTwoLayers();

        // (5 x 10 + 5 x 30) / 10 = 20.
        $this->assertEqualsWithDelta(20.0, (float) $this->ctx['item']->fresh()->avg_cost, 0.0001);

        $this->sellTwo();

        $this->assertEqualsWithDelta(20.0, $this->outMovementCost(), 0.0001, 'stock ledger');
        $this->assertEqualsWithDelta(20.0, $this->bookedUnitCost(), 0.0001, 'cost of goods sold');
    }

    /**
     * "Specific" is offered in the item and company forms but nothing
     * implements it — StockService::handleOut only branches FIFO and LIFO, so
     * it silently costs at the weighted average instead.
     */
    public function test_choosing_specific_identification_silently_costs_at_the_average(): void
    {
        $this->useCompanyMethod(CostingMethod::SPECIFIC);
        $this->receiveTwoLayers();

        $this->sellTwo();

        $this->assertEqualsWithDelta(20.0, $this->outMovementCost(), 0.0001);
    }

    /**
     * An item may carry its own costing_method, overriding the company default
     * (Item::effectiveCostingMethod). The stock ledger honours that; the sale's
     * cost of goods has to honour the same one, or an item's stock is issued at
     * one cost and booked at another.
     */
    public function test_an_item_costing_override_is_honoured_by_both_the_ledger_and_the_books(): void
    {
        $this->useCompanyMethod(CostingMethod::WEIGHTED_AVERAGE);
        $this->ctx['item']->update(['costing_method' => CostingMethod::FIFO->value]);

        $this->receiveTwoLayers();
        $this->sellTwo();

        // FIFO, because that is what the ITEM is on — not the company's 20.
        $this->assertEqualsWithDelta(
            10.0,
            $this->outMovementCost(),
            0.0001,
            'the stock ledger follows the item override',
        );
        $this->assertEqualsWithDelta(
            10.0,
            $this->bookedUnitCost(),
            0.0001,
            'cost of goods must follow the same override the ledger used',
        );
    }
}
