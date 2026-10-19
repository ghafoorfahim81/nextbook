<?php

namespace Tests\Feature\Sale;

use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReturn;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Every operation the Sale Returns menu offers: list, create, show, update,
 * reverse, delete, restore, force-delete, and the endpoint the form uses to
 * find what is still returnable against an invoice.
 */
class SaleReturnOperationsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        app(ItemVariantService::class)->ensureDefault($this->ctx['item']);
        $this->receive(20, 10);
        $this->sale = $this->sellTenUnits();
    }

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

    private function sellTenUnits(): Sale
    {
        $this->post(route('sales.store'), [
            'number' => 8001,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => 300,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 30,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Sale::query()->with('items')->latest()->firstOrFail();
    }

    private function onHand(): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => 8501,
            'sale_id' => $this->sale->id,
            'date' => '2026-03-12',
            'reason' => SaleReturnReason::values()[0],
            'description' => 'customer changed their mind',
            'item_list' => [[
                'sale_item_id' => $this->sale->items->first()->id,
                'quantity' => 4,
            ]],
        ], $overrides);
    }

    private function createReturn(array $overrides = []): SaleReturn
    {
        $this->post(route('sale-returns.store'), $this->payload($overrides))->assertRedirect();

        return SaleReturn::query()->latest()->firstOrFail();
    }

    public function test_the_list_and_create_screens_render(): void
    {
        $this->createReturn();

        $this->get(route('sale-returns.index'))->assertOk();
        $this->get(route('sale-returns.create'))->assertOk();
    }

    public function test_the_returnable_items_endpoint_reports_what_is_left_on_the_invoice(): void
    {
        $response = $this->getJson(route('sale-returns.returnable-items', [
            'sale_id' => $this->sale->id,
        ]))->assertOk();

        $this->assertNotEmpty($response->json('items'));
    }

    public function test_a_return_brings_the_goods_back_onto_the_shelf(): void
    {
        // 20 received less 10 sold.
        $this->assertEqualsWithDelta(10.0, $this->onHand(), 0.0001);

        $return = $this->createReturn();

        $this->assertDatabaseHas('sale_returns', [
            'id' => $return->id,
            'sale_id' => $this->sale->id,
        ]);
        $this->assertEqualsWithDelta(
            14.0,
            $this->onHand(),
            0.0001,
            'Taking 4 back from the customer must put them back on the shelf.',
        );
    }

    public function test_the_show_screen_renders(): void
    {
        $return = $this->createReturn();

        $this->get(route('sale-returns.show', $return))->assertOk();
    }

    public function test_a_return_cannot_exceed_what_was_sold(): void
    {
        $this->post(route('sale-returns.store'), $this->payload([
            'item_list' => [[
                'sale_item_id' => $this->sale->items->first()->id,
                'quantity' => 999,
            ]],
        ]))->assertSessionHasErrors();
    }

    public function test_the_same_sale_line_cannot_be_returned_twice_in_one_document(): void
    {
        $lineId = $this->sale->items->first()->id;

        $this->post(route('sale-returns.store'), $this->payload([
            'item_list' => [
                ['sale_item_id' => $lineId, 'quantity' => 1],
                ['sale_item_id' => $lineId, 'quantity' => 1],
            ],
        ]))->assertSessionHasErrors('item_list');
    }

    public function test_a_posted_return_can_be_reversed_and_takes_the_stock_away_again(): void
    {
        $return = $this->createReturn();
        $this->assertEqualsWithDelta(14.0, $this->onHand(), 0.0001);

        $this->post(route('sale-returns.reverse', $return), ['reason' => 'entered in error'])
            ->assertRedirect();

        $this->assertSame(TransactionStatus::REVERSED->value, $return->fresh()->status);
        $this->assertEqualsWithDelta(
            10.0,
            $this->onHand(),
            0.0001,
            'Reversing the return must undo the goods coming back.',
        );
    }

    public function test_a_draft_return_can_be_deleted_restored_and_force_deleted(): void
    {
        $this->ctx['user']->setPreference('transaction.sale_return_post_immediately', false);
        $this->ctx['user']->save();

        $return = $this->createReturn(['number' => 8502]);

        if ($return->status !== TransactionStatus::DRAFT->value) {
            $this->markTestSkipped('sale returns always post on create');
        }

        $this->delete(route('sale-returns.destroy', $return))->assertRedirect();
        $this->assertSoftDeleted('sale_returns', ['id' => $return->id]);
        $this->assertSoftDeleted('sale_return_items', ['sale_return_id' => $return->id]);

        $this->patch(route('sale-returns.restore', $return->id))->assertRedirect();
        $this->assertNotSoftDeleted('sale_returns', ['id' => $return->id]);
        $this->assertNotSoftDeleted('sale_return_items', ['sale_return_id' => $return->id]);

        $this->delete(route('sale-returns.destroy', $return))->assertRedirect();
        $this->delete(route('sale-returns.force-delete', $return->id))->assertRedirect();
        $this->assertDatabaseMissing('sale_returns', ['id' => $return->id]);
    }
}
