<?php

namespace Tests\Feature\Inventory;

use App\Enums\PurchaseReturnReason;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockBalance;
use App\Models\Purchase\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Buy ten onto an empty shelf, write two off, then try to undo the purchase.
 *
 * The purchase wants to take ten units back, and only eight are left. There is
 * no honest way to do that: the two that were written off are gone, and undoing
 * the receipt would either invent stock or drive the shelf negative. The
 * adjustment has to be reversed first.
 */
class ReverseAfterAdjustmentTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        foreach ([
            ['Inventory Shrinkage & Wastage', '9040', 'inventory-shrinkage-and-wastage'],
            ['Inventory Adjustments', '9050', 'inventory-adjustments'],
        ] as [$name, $number, $slug]) {
            Account::factory()->create([
                'branch_id' => $this->ctx['branch']->id,
                'name' => $name,
                'number' => $number,
                'slug' => $slug,
                'account_type_id' => $this->ctx['account_types']['expense']->id,
                'is_main' => true,
                'is_active' => true,
            ]);
        }
    }

    private function buyTen(): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(3000, 3999),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => 10 * 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 100,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function writeOffTwo(): StockAdjustment
    {
        $this->post(route('stock-adjustments.store'), [
            'date' => now()->toDateString(),
            'reason' => 'damage',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'notes' => 'broken in transit',
            'items' => [[
                'item_id' => $this->ctx['item']->id,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'quantity' => 2,
            ]],
        ])->assertRedirect(route('stock-adjustments.index'));

        return StockAdjustment::query()->orderByDesc('id')->firstOrFail();
    }

    private function onHand(): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->sum('quantity');
    }

    private function inventoryGl(): float
    {
        return (float) DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transaction_lines.account_id', $this->ctx['accounts']['inventory-stock']->id)
            ->whereIn('transactions.status', ['posted', 'reversed'])
            ->whereNull('transaction_lines.deleted_at')
            ->selectRaw('COALESCE(SUM(transaction_lines.debit - transaction_lines.credit), 0) AS b')
            ->value('b');
    }

    public function test_the_shelf_and_the_ledger_after_the_write_off(): void
    {
        $this->buyTen();
        $this->writeOffTwo();

        $this->assertEqualsWithDelta(8.0, $this->onHand(), 0.0001);

        // 1,000 received less 200 written off.
        $this->assertEqualsWithDelta(800.0, $this->inventoryGl(), 0.0001);
    }

    public function test_reversing_the_purchase_is_refused_while_the_write_off_stands(): void
    {
        $purchase = $this->buyTen();
        $this->writeOffTwo();

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertSessionHasErrors('stock');

        $message = implode(' ', session('errors')->all());

        $this->assertSame(__('general.cannot_reverse_stock_already_gone'), $message);

        // Nothing moved.
        $this->assertSame(TransactionStatus::POSTED->value, $purchase->fresh()->status);
        $this->assertEqualsWithDelta(8.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(800.0, $this->inventoryGl(), 0.0001);
    }

    public function test_the_shelf_never_goes_negative(): void
    {
        $purchase = $this->buyTen();
        $this->writeOffTwo();

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled']);

        $this->assertGreaterThanOrEqual(
            0.0,
            $this->onHand(),
            'A refused reversal must never leave the warehouse owing stock.',
        );
    }

    /**
     * The other way out, and the one the refusal message suggests: keep the
     * write-off and send the supplier what is actually left.
     */
    public function test_the_eight_that_remain_can_be_returned_to_the_supplier(): void
    {
        $purchase = $this->buyTen();
        $this->writeOffTwo();

        $this->post(route('purchase-returns.store'), [
            'number' => random_int(3500, 3999),
            'purchase_id' => $purchase->id,
            'date' => now()->toDateString(),
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'sending back what survived',
            'item_list' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => 8,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Two were written off and eight went back, so nothing is left and the
        // inventory account is square.
        $this->assertEqualsWithDelta(0.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->inventoryGl(), 0.0001);
    }

    public function test_returning_more_than_survived_is_refused(): void
    {
        $purchase = $this->buyTen();
        $this->writeOffTwo();

        // All ten are still returnable as far as the purchase line knows, but
        // only eight are on the shelf to send.
        $this->post(route('purchase-returns.store'), [
            'number' => random_int(3500, 3999),
            'purchase_id' => $purchase->id,
            'date' => now()->toDateString(),
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'more than there is',
            'item_list' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => 10,
            ]],
        ]);

        $this->assertNotNull(session('errors'), 'Returning stock that is not there must be refused.');
        $this->assertEqualsWithDelta(8.0, $this->onHand(), 0.0001);
    }

    public function test_reversing_the_write_off_first_lets_the_purchase_go_back(): void
    {
        $purchase = $this->buyTen();
        $adjustment = $this->writeOffTwo();

        $this->post(route('stock-adjustments.reverse', $adjustment), ['reason' => 'found again'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(10.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(1000.0, $this->inventoryGl(), 0.0001);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $purchase->fresh()->status);

        // Back to the empty shelf it started from, with nothing left in the
        // inventory account.
        $this->assertEqualsWithDelta(0.0, $this->onHand(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->inventoryGl(), 0.0001);
    }
}
