<?php

namespace Tests\Feature\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Sale\Sale;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * What a purchase return costs, and what it does to the average.
 *
 * A return is not an ordinary issue. The supplier credits what they were paid,
 * so the goods have to leave at the price that purchase paid and out of that
 * purchase's own layers — and the cost has to come back OUT of the average it
 * was blended into. Costed off the front of the FIFO queue instead, a return of
 * goods bought at 100 relieved inventory at an opening layer's 50, leaving the
 * ledger and the stock disagreeing by the difference.
 */
class PurchaseReturnCostingTest extends TestCase
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

    /** Opening stock, owing nothing to any purchase document. */
    private function openWith(float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => '2026-03-01',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    private function buy(float $quantity, float $unitPrice): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(7000, 7999),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->latest()->firstOrFail();
    }

    private function sell(float $quantity, float $unitPrice = 200): Sale
    {
        $this->post(route('sales.store'), [
            'number' => random_int(8000, 8999),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-11',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->with('items')->latest()->firstOrFail();
    }

    private function returnPurchase(Purchase $purchase, float $quantity): PurchaseReturn
    {
        $this->post(route('purchase-returns.store'), [
            'number' => random_int(7500, 7999),
            'purchase_id' => $purchase->id,
            'date' => '2026-03-14',
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'sent back to the supplier',
            'item_list' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => $quantity,
            ]],
        ])->assertRedirect();

        return PurchaseReturn::query()->latest()->firstOrFail();
    }

    private function returnSale(Sale $sale, float $quantity): void
    {
        $this->post(route('sale-returns.store'), [
            'number' => random_int(8500, 8999),
            'sale_id' => $sale->id,
            'date' => '2026-03-13',
            'reason' => SaleReturnReason::values()[0],
            'description' => 'customer brought them back',
            'item_list' => [[
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => $quantity,
            ]],
        ])->assertRedirect();
    }

    private function itemAverage(): float
    {
        return (float) $this->ctx['item']->fresh()->avg_cost;
    }

    private function variantAverage(): float
    {
        return (float) app(ItemVariantService::class)
            ->ensureDefault($this->ctx['item'])
            ->fresh()
            ->avg_cost;
    }

    private function onHand(): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity');
    }

    private function returnMovement(PurchaseReturn $return): StockMovement
    {
        return StockMovement::query()
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->firstOrFail();
    }

    public function test_the_return_leaves_at_the_price_the_purchase_paid(): void
    {
        // An older, cheaper layer sits in front of the purchase in the FIFO
        // queue. That is the layer the return used to be costed from.
        $this->openWith(10, 50);
        $purchase = $this->buy(5, 100);

        $return = $this->returnPurchase($purchase, 5);

        $this->assertEqualsWithDelta(
            100.0,
            (float) $this->returnMovement($return)->unit_cost,
            0.0001,
            'Goods bought at 100 go back to the supplier at 100, not at the oldest layer on the shelf.',
        );
    }

    public function test_the_return_empties_the_layers_that_purchase_brought_in(): void
    {
        $this->openWith(10, 50);
        $purchase = $this->buy(5, 100);

        $this->returnPurchase($purchase, 5);

        $purchaseLayer = StockMovement::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->where('movement_type', StockMovementType::IN->value)
            ->firstOrFail();

        $this->assertEqualsWithDelta(
            0.0,
            (float) $purchaseLayer->qty_remaining,
            0.0001,
            'The returned goods are the ones that purchase brought in.',
        );

        $openingLayer = StockMovement::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('source', StockSourceType::OPENING->value)
            ->firstOrFail();

        $this->assertEqualsWithDelta(
            10.0,
            (float) $openingLayer->qty_remaining,
            0.0001,
            'The opening stock is untouched — it was never sent back.',
        );
    }

    public function test_the_return_takes_its_cost_back_out_of_the_average(): void
    {
        $this->openWith(10, 50);
        $this->sell(5);

        $purchase = $this->buy(5, 100);

        // 5 left at 50 blended with 5 at 100 is the running 75.
        $this->assertEqualsWithDelta(75.0, $this->itemAverage(), 0.0001);

        $this->returnPurchase($purchase, 5);

        // The 100s went back, so only the opening stock is left to carry a cost.
        $this->assertEqualsWithDelta(5.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(50.0, $this->itemAverage(), 0.0001);
        $this->assertEqualsWithDelta(50.0, $this->variantAverage(), 0.0001);
    }

    /**
     * The sequence as reported: opening 10 at 50, sell 5, buy 5 at 100, take the
     * sale back, then send the purchase back. Nothing bought at 100 is still
     * owned, so all 10 units on the shelf are opening stock at 50.
     */
    public function test_the_full_reported_sequence_lands_on_the_opening_cost(): void
    {
        $this->openWith(10, 50);
        $sale = $this->sell(5);
        $purchase = $this->buy(5, 100);

        $this->returnSale($sale, 5);
        $this->returnPurchase($purchase, 5);

        $this->assertEqualsWithDelta(10.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(50.0, $this->itemAverage(), 0.0001);
        $this->assertEqualsWithDelta(50.0, $this->variantAverage(), 0.0001);
    }
}
