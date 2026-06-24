<?php

namespace Tests\Integration;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Sale\Sale;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class StockReservationIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function seedStock(float $quantity): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'reservation-seed',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    private function outPayload(float $quantity): array
    {
        return [
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::OUT->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::SALE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-19',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => Sale::class,
            'reference_id' => $this->ctx['item']->id,
        ];
    }

    public function test_reserve_and_release_adjust_reserved_out(): void
    {
        $this->seedStock(10);
        $stockService = app(StockService::class);

        $stockService->reserve($this->outPayload(4));

        $balance = StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->firstOrFail();

        $this->assertSame('4.0000', (string) $balance->reserved_out);
        $this->assertSame('10.0000', (string) $balance->quantity);

        $stockService->release($this->outPayload(4));

        $this->assertSame('0.0000', (string) $balance->fresh()->reserved_out);
    }

    public function test_release_floors_at_zero(): void
    {
        $this->seedStock(10);
        $stockService = app(StockService::class);

        // Releasing more than reserved must never produce a negative reservation.
        $stockService->release($this->outPayload(4));

        $balance = StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->firstOrFail();

        $this->assertSame('0.0000', (string) $balance->reserved_out);
    }

    public function test_ensure_reserved_availability_blocks_when_other_drafts_hold_stock(): void
    {
        $this->seedStock(10);
        $stockService = app(StockService::class);

        // Two separate drafts each reserve all 10 (over-committed): reserved_out = 20.
        $stockService->reserve($this->outPayload(10)); // draft A (the one being posted)
        $stockService->reserve($this->outPayload(10)); // draft B (another user)

        // Posting draft A: its own 10 is excluded, leaving 10 reserved by others
        // against only 10 on hand => nothing available for A, so it is blocked.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $stockService->ensureReservedAvailability($this->outPayload(10));
    }

    public function test_ensure_reserved_availability_ignores_own_reservation(): void
    {
        $this->seedStock(10);
        $stockService = app(StockService::class);

        // Only this document's own reservation exists, so it is not blocked by itself.
        $stockService->reserve($this->outPayload(10));

        $stockService->ensureReservedAvailability($this->outPayload(10));

        // No exception => reached here.
        $this->assertTrue(true);
    }

    public function test_draft_sale_reserves_and_post_releases_then_deducts(): void
    {
        $this->seedStock(10);
        set_user_preference('transaction.sale_post_immediately', false, $this->ctx['user']);

        $this->post(route('sales.store'), [
            'number' => 7001,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 120,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 6,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->latest()->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $sale->status);

        $balance = StockBalance::query()->where('item_id', $this->ctx['item']->id)->firstOrFail();
        // Draft holds the stock but does not deduct it.
        $this->assertSame('6.0000', (string) $balance->reserved_out);
        $this->assertSame('10.0000', (string) $balance->quantity);

        // Posting the draft clears the reservation and deducts the stock.
        $this->post(route('sales.post', $sale->id))->assertRedirect();

        $balance = $balance->fresh();
        $this->assertSame('0.0000', (string) $balance->reserved_out);
        $this->assertSame('4.0000', (string) $balance->quantity);
    }

    public function test_deleting_draft_sale_releases_reservation(): void
    {
        $this->seedStock(10);
        set_user_preference('transaction.sale_post_immediately', false, $this->ctx['user']);

        $this->post(route('sales.store'), [
            'number' => 7002,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 120,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 6,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->latest()->firstOrFail();
        $this->assertSame('6.0000', (string) StockBalance::query()->where('item_id', $this->ctx['item']->id)->firstOrFail()->reserved_out);

        $this->delete(route('sales.destroy', $sale))->assertRedirect(route('sales.index'));

        $this->assertSame('0.0000', (string) StockBalance::query()->where('item_id', $this->ctx['item']->id)->firstOrFail()->reserved_out);
    }
}
