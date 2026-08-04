<?php

namespace Tests\Feature\Purchase;

use App\Models\Administration\Currency;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockMovement;
use App\Models\Purchase\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Amounts live in two different spaces:
 *   - transaction_lines are in the transaction's currency, and every report reads
 *     them as `line * transactions.rate`;
 *   - stock costing (stock_movements.unit_cost, items.avg_cost) has no currency of
 *     its own and is always home currency.
 *
 * A foreign-currency purchase is the only place both meet, so it is the case that
 * catches a missing conversion in either direction.
 */
class PurchaseForeignCurrencyTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->usd = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 70,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'us.png',
        ]);
    }

    public function test_foreign_currency_purchase_costs_stock_in_home_currency(): void
    {
        $this->postUsdPurchase();

        $movement = StockMovement::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('reference_type', Purchase::class)
            ->firstOrFail();

        // 50 USD at 69.7 => 3,485 AFN per unit.
        $this->assertEqualsWithDelta(3485.0, (float) $movement->unit_cost, 0.0001);
        $this->assertEqualsWithDelta(3485.0, (float) Item::findOrFail($this->ctx['item']->id)->avg_cost, 0.0001);
    }

    public function test_foreign_currency_purchase_posts_gl_lines_in_transaction_currency(): void
    {
        $purchase = $this->postUsdPurchase();

        $inventoryDebit = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', Purchase::class)
            ->where('t.reference_id', $purchase->id)
            ->where('tl.account_id', Item::findOrFail($this->ctx['item']->id)->asset_account_id)
            ->sum('tl.debit');

        // The line stays in USD; the rate on the header carries it to home currency.
        $this->assertEqualsWithDelta(50.0, (float) $inventoryDebit, 0.0001);
    }

    /**
     * The dashboard / inventory-stock report values stock from stock_movements while
     * the balance sheet values it from the GL. Both must land on the same number.
     */
    public function test_stock_movement_value_matches_the_general_ledger(): void
    {
        $purchase = $this->postUsdPurchase();

        $movementValue = (float) StockMovement::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('reference_type', Purchase::class)
            ->get()
            ->sum(fn ($movement) => (float) $movement->quantity * (float) $movement->unit_cost);

        $glValue = (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', Purchase::class)
            ->where('t.reference_id', $purchase->id)
            ->where('tl.account_id', Item::findOrFail($this->ctx['item']->id)->asset_account_id)
            ->selectRaw('COALESCE(SUM((tl.debit - tl.credit) * t.rate), 0) as value')
            ->value('value');

        $this->assertEqualsWithDelta($glValue, $movementValue, 0.0001);
    }

    public function test_zero_exchange_rate_is_rejected(): void
    {
        $response = $this->post(route('purchases.store'), $this->purchasePayload(['rate' => 0]));

        $response->assertSessionHasErrors('rate');
        $this->assertDatabaseCount('purchases', 0);
    }

    private function postUsdPurchase(): Purchase
    {
        $this->post(route('purchases.store'), $this->purchasePayload())
            ->assertRedirect(route('purchases.index'));

        return Purchase::query()->latest()->firstOrFail();
    }

    private function purchasePayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 7001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-08-03',
            'transaction_total' => 50,
            'currency_id' => $this->usd->id,
            'rate' => 69.7,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'discount' => 0,
            'discount_type' => 'percentage',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'foreign currency purchase',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 1,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 50,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ], $overrides);
    }
}
