<?php

namespace Tests\Feature\Purchase;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Purchase\Purchase;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Reversing a purchase must undo both halves of what posting it did: the
 * quantity it put on the shelf, and the cost it blended into the average.
 *
 * The average is the half that is easy to miss, because nothing about the
 * stock figures looks wrong afterwards — the item simply carries the cost of
 * goods the business never ended up owning, and every COGS figure taken from
 * it from then on is quietly too high.
 */
class PurchaseReversalTest extends TestCase
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

    /** Opening stock, so the purchase blends into an existing average. */
    private function receive(float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => '2026-03-01',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'opening',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    private function onHand(): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity');
    }

    private function avgCost(): float
    {
        return (float) $this->ctx['item']->fresh()->avg_cost;
    }

    private function buy(float $quantity, float $unitPrice): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => 7100,
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

        return Purchase::query()->latest()->firstOrFail();
    }

    public function test_reversing_a_purchase_takes_back_both_the_quantity_and_its_share_of_the_average(): void
    {
        $this->receive(10, 20);
        $this->assertEqualsWithDelta(20.0, $this->avgCost(), 0.0001);

        // 5 more at 30 blends to (10 x 20 + 5 x 30) / 15 = 23.3333.
        $purchase = $this->buy(5, 30);

        $this->assertEqualsWithDelta(15.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(23.3333, $this->avgCost(), 0.001);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(
            10.0,
            $this->onHand(),
            0.0001,
            'Reversing a purchase must take the received quantity back off the shelf.',
        );
        $this->assertEqualsWithDelta(
            20.0,
            $this->avgCost(),
            0.0001,
            'Reversing a purchase must take its cost back out of the average, '
            .'leaving the average the stock had before it.',
        );
    }

    public function test_the_purchase_is_marked_reversed_and_its_stock_movement_voided(): void
    {
        $this->receive(10, 20);
        $purchase = $this->buy(5, 30);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'status' => 'reversed',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_id' => $purchase->id,
            'status' => StockStatus::VOIDED->value,
        ]);
    }
}
