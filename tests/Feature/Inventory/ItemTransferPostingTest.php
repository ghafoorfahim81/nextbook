<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransferStatus;
use App\Http\Resources\Inventory\ItemResource;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockMovement;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Posting, reversing and costing an item transfer.
 *
 * A transfer is the one document that moves the same goods twice, which makes
 * it the easiest one to get wrong: its movements have to be posted (not left as
 * drafts), its reversal has to take BOTH legs back out of the item's history,
 * and neither leg may re-price stock the business already owned.
 */
class ItemTransferPostingTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Warehouse $toWarehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->toWarehouse = Warehouse::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Secondary Warehouse',
        ]);

        // Post-immediately is on by default, which would post a transfer the
        // moment it is created. These tests need the draft -> post path.
        set_user_preference('transaction.item_transfer_post_immediately', false, $this->ctx['user']);
    }

    public function test_posting_a_transfer_posts_its_stock_movements(): void
    {
        $this->seedStock(10, 15);
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15);

        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();

        $movements = StockMovement::query()
            ->where('reference_type', ItemTransfer::class)
            ->where('reference_id', $transfer->id)
            ->get();

        $this->assertCount(2, $movements);
        $this->assertTrue(
            $movements->every(fn (StockMovement $m) => $m->status === StockStatus::POSTED),
            'A posted transfer must not leave its stock movements in draft.'
        );
    }

    public function test_a_draft_transfer_cannot_be_reversed(): void
    {
        $this->seedStock(10, 15);
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15);

        $this->assertEquals(TransferStatus::PENDING, $transfer->status);

        $this->post(route('item-transfers.reverse', $transfer), ['reason' => 'mistake'])
            ->assertStatus(422);

        $transfer->refresh();
        $this->assertEquals(TransferStatus::PENDING, $transfer->status);
    }

    public function test_reversing_a_posted_transfer_voids_both_legs_and_restores_stock(): void
    {
        $this->seedStock(10, 15);
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15);

        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();
        $this->post(route('item-transfers.reverse', $transfer), ['reason' => 'sent to the wrong shop'])
            ->assertRedirect();

        $transfer->refresh();
        $this->assertEquals(TransferStatus::CANCELLED, $transfer->status);

        $live = StockMovement::query()
            ->where('reference_type', ItemTransfer::class)
            ->where('reference_id', $transfer->id)
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->count();

        $this->assertSame(0, $live, 'Reversing a transfer must leave no live movement behind.');

        // The goods are back where they started.
        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 10.0000,
        ]);
        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->toWarehouse->id,
            'quantity' => 0.0000,
        ]);
    }

    public function test_a_reversed_transfer_is_left_out_of_total_in_and_total_out(): void
    {
        $this->seedStock(10, 15);
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15);

        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();
        $this->post(route('item-transfers.reverse', $transfer), ['reason' => 'undo'])->assertRedirect();

        $item = Item::query()->with('stocks.unitMeasure', 'unitMeasure')->findOrFail($this->ctx['item']->id);
        $payload = ItemResource::make($item)->toArray(request());

        // Only the seeded receipt of 10 counts: the transfer's own two legs and
        // the two that undid them are all voided.
        $this->assertSame('10.00', $payload['stock_count']);
        $this->assertSame('0.00', $payload['stock_out_count']);
    }

    public function test_in_history_skips_reversed_movements(): void
    {
        $this->seedStock(10, 15);
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15);

        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();
        $this->post(route('item-transfers.reverse', $transfer), ['reason' => 'undo'])->assertRedirect();

        $response = $this->getJson(route('items.in-records', $this->ctx['item']));
        $response->assertOk();

        $sources = collect($response->json('data'))->pluck('source_type');
        $this->assertNotContains(
            StockSourceType::ITEM_TRANSFER->value,
            $sources,
            'A reversed transfer must not appear in the item In history.'
        );
    }

    public function test_a_transfer_cost_posts_to_the_item_transfer_expense_account(): void
    {
        $this->seedStock(10, 15);

        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15, extra: [
            'has_transfer_cost' => true,
            'transfer_cost' => 250,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
        ]);

        $this->assertTrue((bool) $transfer->has_transfer_cost);
        $this->assertSame(
            $this->ctx['accounts']['item-transfer-expense']->id,
            $transfer->expense_account_id,
            'The expense side defaults to the Item Transfer Expense account.'
        );

        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();

        $transaction = Transaction::query()
            ->where('reference_type', ItemTransfer::class)
            ->where('reference_id', $transfer->id)
            ->firstOrFail();

        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->ctx['accounts']['item-transfer-expense']->id,
            'debit' => 250.0000,
        ]);
        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'credit' => 250.0000,
        ]);
    }

    public function test_the_switch_off_clears_a_cost_that_was_typed_and_abandoned(): void
    {
        $this->seedStock(10, 15);

        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15, extra: [
            'has_transfer_cost' => false,
            'transfer_cost' => 999,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ]);

        $this->assertFalse((bool) $transfer->has_transfer_cost);
        $this->assertNull($transfer->transfer_cost);
        $this->assertNull($transfer->bank_account_id);

        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();

        $this->assertSame(
            0,
            TransactionLine::query()->whereIn(
                'transaction_id',
                Transaction::query()
                    ->where('reference_type', ItemTransfer::class)
                    ->where('reference_id', $transfer->id)
                    ->pluck('id')
            )->count(),
            'A transfer with the cost switch off must post no freight voucher.'
        );
    }

    public function test_a_transfer_does_not_re_price_the_item_average(): void
    {
        $this->seedStock(10, 15);

        $item = Item::findOrFail($this->ctx['item']->id);
        $averageBefore = (float) $item->avg_cost;

        // Moved at a deliberately wrong price: an internal move must not blend
        // it into the average the way a purchase would.
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 999);
        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();

        $this->assertEqualsWithDelta($averageBefore, (float) $item->fresh()->avg_cost, 0.0001);
    }

    public function test_a_transfer_posted_on_creation_also_posts_its_movements(): void
    {
        set_user_preference('transaction.item_transfer_post_immediately', true, $this->ctx['user']);

        $this->seedStock(10, 15);
        $transfer = $this->createTransfer(quantity: 4, unitPrice: 15);

        $this->assertEquals(TransferStatus::COMPLETED, $transfer->status);

        $draft = StockMovement::query()
            ->where('reference_type', ItemTransfer::class)
            ->where('reference_id', $transfer->id)
            ->where('status', StockStatus::DRAFT->value)
            ->count();

        $this->assertSame(0, $draft);
    }

    public function test_posting_a_transfer_re_derives_the_variant_average_cost(): void
    {
        // Two receipts at different costs: the true blended average is
        // ((6 x 10) + (4 x 20)) / 10 = 14.
        $this->seedStock(6, 10);
        $this->seedStock(4, 20);

        $variant = app(ItemVariantService::class)->ensureDefault(Item::findOrFail($this->ctx['item']->id));

        // Nudge the stored figure off so the assertion can only pass if the
        // transfer actually re-derives it rather than leaving it alone.
        $variant->forceFill(['avg_cost' => 999])->save();

        $transfer = $this->createTransfer(quantity: 3, unitPrice: 14);
        $this->patch(route('item-transfers.complete', $transfer))->assertRedirect();

        $this->assertEqualsWithDelta(14.0, (float) $variant->fresh()->avg_cost, 0.0001);
    }

    private function seedStock(float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'date' => '2026-03-10',
            'batch' => null,
            'expire_date' => null,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    private function createTransfer(float $quantity, float $unitPrice, array $extra = []): ItemTransfer
    {
        $this->post(route('item-transfers.store'), array_merge([
            'date' => '2026-03-19',
            'from_warehouse_id' => $this->ctx['warehouse']->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'remarks' => 'stock transfer',
            'items' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'quantity' => $quantity,
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => $unitPrice,
                ],
            ],
        ], $extra))->assertRedirect(route('item-transfers.index'));

        return ItemTransfer::query()->latest()->firstOrFail();
    }
}
