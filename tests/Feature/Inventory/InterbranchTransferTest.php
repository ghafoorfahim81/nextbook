<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\StockMovement;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\Transaction\TransactionLine;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Phase 4: stock sent to another branch is in transit until that branch takes
 * it in — and may arrive short.
 *
 * Kabul sends, Herat receives. Between the two the goods are in neither
 * warehouse and their value sits in Goods in Transit; on receipt Herat books
 * what arrived and the shortfall goes to inventory shrinkage, so Goods in
 * Transit clears.
 */
class InterbranchTransferTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Branch $herat;

    private Warehouse $heratWarehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->herat = Branch::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'name' => 'Herat',
        ]);
        $this->heratWarehouse = Warehouse::factory()->create([
            'branch_id' => $this->herat->id,
            'name' => 'Herat Warehouse',
        ]);

        set_user_preference('transaction.item_transfer_post_immediately', false, $this->ctx['user']);

        $this->seedStock(20, 10);
    }

    public function test_sending_to_another_branch_puts_the_goods_in_transit(): void
    {
        $transfer = $this->send(quantity: 8, unitPrice: 10);

        $this->assertSame($this->herat->id, $transfer->to_branch_id);
        $this->assertTrue($transfer->isInTransit());
        $this->assertSame(12.0, $this->onHand($this->ctx['warehouse']->id));
        // Not in Herat yet.
        $this->assertSame(0.0, $this->onHand($this->heratWarehouse->id));
        $this->assertSame(80.0, $this->balance('goods-in-transit'));
    }

    public function test_the_receiving_branch_takes_the_goods_in_and_books_a_shortfall(): void
    {
        $transfer = $this->send(quantity: 8, unitPrice: 10);
        $line = $transfer->items->first();

        // Only someone working in Herat may receive it.
        $this->post(route('branches.switch'), ['branch_id' => $this->herat->id]);
        $this->post(route('item-transfers.receive', $transfer), ['received' => [$line->id => 6]])
            ->assertSessionHasNoErrors();

        $transfer->refresh();

        $this->assertFalse($transfer->isInTransit());
        $this->assertNotNull($transfer->received_at);
        $this->assertSame(6.0, (float) $line->fresh()->received_quantity);
        $this->assertSame(6.0, $this->onHand($this->heratWarehouse->id));
        // Goods in Transit clears; the 2 that never came cost 20.
        $this->assertSame(0.0, $this->balance('goods-in-transit'));
        $this->assertSame(20.0, $this->balance('inventory-shrinkage-and-wastage'));
    }

    public function test_the_sending_branch_cannot_receive_its_own_transfer(): void
    {
        $transfer = $this->send(quantity: 8, unitPrice: 10);

        $this->post(route('item-transfers.receive', $transfer))
            ->assertSessionHasErrors('status');

        $this->assertTrue($transfer->fresh()->isInTransit());
    }

    public function test_more_cannot_arrive_than_was_sent(): void
    {
        $transfer = $this->send(quantity: 8, unitPrice: 10);

        $this->post(route('branches.switch'), ['branch_id' => $this->herat->id]);
        $this->post(route('item-transfers.receive', $transfer), ['received' => [$transfer->items->first()->id => 9]])
            ->assertSessionHasErrors();

        $this->assertTrue($transfer->fresh()->isInTransit());
    }

    public function test_reversing_a_transfer_in_transit_puts_everything_back(): void
    {
        $transfer = $this->send(quantity: 8, unitPrice: 10);

        $this->post(route('item-transfers.reverse', $transfer), ['reason' => 'wrong branch'])
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED, $transfer->fresh()->status);
        $this->assertSame(20.0, $this->onHand($this->ctx['warehouse']->id));
        $this->assertSame(0.0, $this->balance('goods-in-transit'));
    }

    public function test_the_receiving_branch_cannot_reverse_the_transfer(): void
    {
        $transfer = $this->send(quantity: 8, unitPrice: 10);

        $this->post(route('branches.switch'), ['branch_id' => $this->herat->id]);
        $this->post(route('item-transfers.reverse', $transfer), ['reason' => 'not ours'])
            ->assertSessionHasErrors('status');

        $this->assertTrue($transfer->fresh()->isInTransit());
        $this->assertSame(80.0, $this->balance('goods-in-transit'));
    }

    public function test_a_transfer_within_one_branch_still_moves_at_once(): void
    {
        $shelf = Warehouse::factory()->create(['branch_id' => $this->ctx['branch']->id, 'name' => 'Back Shelf']);

        $transfer = $this->send(quantity: 5, unitPrice: 10, to: $shelf);

        $this->assertFalse($transfer->isInTransit());
        $this->assertNotNull($transfer->received_at);
        $this->assertSame(5.0, $this->onHand($shelf->id));
        $this->assertSame(0.0, $this->balance('goods-in-transit'));
    }

    private function send(float $quantity, float $unitPrice, ?Warehouse $to = null): ItemTransfer
    {
        $this->post(route('item-transfers.store'), [
            'date' => '2026-03-19',
            'from_warehouse_id' => $this->ctx['warehouse']->id,
            'to_warehouse_id' => ($to ?? $this->heratWarehouse)->id,
            'items' => [[
                'item_id' => $this->ctx['item']->id,
                'quantity' => $quantity,
                'measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
            ]],
        ])->assertSessionHasNoErrors();

        $transfer = ItemTransfer::query()->latest()->firstOrFail();
        $this->patch(route('item-transfers.complete', $transfer))->assertSessionHasNoErrors();

        return $transfer->fresh('items');
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
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    private function onHand(string $warehouseId): float
    {
        return (float) StockMovement::query()->withoutGlobalScopes()
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $this->ctx['item']->id)
            ->whereIn('status', [StockStatus::POSTED->value])
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE -quantity END), 0) AS total")
            ->value('total');
    }

    private function balance(string $slug): float
    {
        return round((float) TransactionLine::query()->withoutGlobalScopes()
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            // A reversed voucher stays in the books beside the one that undoes it.
            ->whereIn('transactions.status', [TransactionStatus::POSTED->value, TransactionStatus::REVERSED->value])
            ->where('transaction_lines.account_id', $this->ctx['accounts'][$slug]->id)
            ->selectRaw('COALESCE(SUM(transaction_lines.debit - transaction_lines.credit), 0) AS total')
            ->value('total'), 2);
    }
}
