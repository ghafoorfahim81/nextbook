<?php

namespace Tests\Feature\Inventory;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use App\Services\ItemOpeningService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * An item opening may be corrected while nothing has drawn on it, and is frozen
 * once stock has been issued against it.
 */
class ItemOpeningUpdateTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_an_unused_opening_can_be_updated(): void
    {
        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);

        $opening = $item->openings()->firstOrFail();
        $this->assertFalse(app(ItemOpeningService::class)->isLocked($opening));

        $response = $this->patch(route('items.update', $item), $this->payload($item, [
            'id' => $opening->id,
            'quantity' => 25,
            'unit_price' => 20,
            'warehouse_id' => $this->ctx['warehouse']->id,
        ]));

        $response->assertSessionHasNoErrors();

        $updated = $item->openings()->firstOrFail();
        $this->assertEquals(25.0, (float) $updated->quantity);
        $this->assertEquals(20.0, (float) $updated->unit_cost);

        // The stock the opening put on the shelf moved with it.
        $this->assertEquals(25.0, (float) StockBalance::where('item_id', $item->id)->sum('quantity'));

        // And so did its valuation: one voucher, 25 x 20.
        $this->assertEquals(500.0, $this->openingVoucherValue($item));
    }

    public function test_an_unused_opening_can_be_removed_entirely(): void
    {
        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);

        $this->patch(route('items.update', $item), $this->payload($item, [
            'quantity' => 0,
            'unit_price' => 0,
            'warehouse_id' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, $item->openings()->count());
        $this->assertEquals(0.0, (float) StockBalance::where('item_id', $item->id)->sum('quantity'));
        $this->assertSame(0, $this->openingVoucherCount($item));
    }

    public function test_an_opening_that_has_been_issued_from_is_locked(): void
    {
        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);
        $this->issue($item, quantity: 4);

        $opening = $item->openings()->firstOrFail();
        $service = app(ItemOpeningService::class);

        $this->assertTrue($service->isLocked($opening));
        $this->assertSame('item.opening_locked_issued', $service->lockReason($opening));
    }

    public function test_updating_an_issued_opening_is_rejected_and_changes_nothing(): void
    {
        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);
        $this->issue($item, quantity: 4);

        $opening = $item->openings()->firstOrFail();

        $response = $this->from(route('items.edit', $item))
            ->patch(route('items.update', $item), $this->payload($item, [
                'id' => $opening->id,
                'quantity' => 999,
                'unit_price' => 1,
                'warehouse_id' => $this->ctx['warehouse']->id,
            ]));

        $response->assertSessionHasErrors('openings');

        $opening->refresh();
        $this->assertEquals(10.0, (float) $opening->quantity);
        $this->assertEquals(15.0, (float) $opening->unit_cost);
    }

    public function test_a_locked_opening_cannot_be_dropped_by_omitting_it(): void
    {
        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);
        $this->issue($item, quantity: 4);

        $this->patch(route('items.update', $item), $this->payload($item, [
            'quantity' => 0,
            'unit_price' => 0,
            'warehouse_id' => null,
        ]))->assertSessionHasErrors('openings');

        $this->assertSame(1, $item->openings()->count());
    }

    public function test_the_edit_screen_flags_which_openings_are_locked(): void
    {
        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);
        $this->issue($item, quantity: 4);

        $this->get(route('items.edit', $item))
            ->assertInertia(fn ($page) => $page
                ->where('item.data.openings.0.is_locked', true)
                ->where('item.data.openings.0.lock_reason', 'item.opening_locked_issued'));
    }

    /**
     * Under weighted average an issue never touches the layer, so the lock has
     * to come from the bucket having been drawn on at all.
     */
    public function test_weighted_average_locks_an_opening_once_stock_is_issued(): void
    {
        $this->ctx['company']->update(['costing_method' => CostingMethod::WEIGHTED_AVERAGE->value]);
        $this->ctx['user']->refresh();
        $this->actingAs($this->ctx['user']);

        $item = $this->ctx['item'];
        $this->postOpening($item, quantity: 10, unitCost: 15);
        $this->issue($item, quantity: 4);

        $opening = $item->openings()->firstOrFail();

        // The layer itself is untouched — proving the lock did not come from
        // qty_remaining, which is what FIFO would have relied on.
        $this->assertEquals(10.0, (float) $opening->qty_remaining);
        $this->assertTrue(app(ItemOpeningService::class)->isLocked($opening));
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    private function postOpening(Item $item, float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $item->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::DRAFT->value,
            'batch' => null,
            'date' => '2026-01-01',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);
    }

    private function issue(Item $item, float $quantity): void
    {
        app(StockService::class)->post([
            'item_id' => $item->id,
            'movement_type' => StockMovementType::OUT->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::SALE->value,
            'unit_cost' => 15,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-02-01',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]);
    }

    /**
     * A full item payload — items.update validates the whole form, not a patch.
     *
     * @param  array<string, mixed>  $opening
     * @return array<string, mixed>
     */
    private function payload(Item $item, array $opening): array
    {
        return [
            'name' => $item->name,
            'code' => $item->code,
            'sku' => $item->sku,
            'item_type' => $item->item_type?->value,
            'unit_measure_id' => $item->unit_measure_id,
            'asset_account_id' => $item->asset_account_id,
            'income_account_id' => $item->income_account_id,
            'cost_account_id' => $item->cost_account_id,
            'size_id' => $item->size_id,
            'openings' => [$opening],
        ];
    }

    private function openingVoucherValue(Item $item): float
    {
        return (float) Transaction::query()
            ->whereIn('reference_type', ['item', Item::class])
            ->where('reference_id', $item->id)
            ->join('transaction_lines', 'transaction_lines.transaction_id', '=', 'transactions.id')
            ->sum('transaction_lines.debit');
    }

    private function openingVoucherCount(Item $item): int
    {
        return Transaction::query()
            ->whereIn('reference_type', ['item', Item::class])
            ->where('reference_id', $item->id)
            ->count();
    }
}
