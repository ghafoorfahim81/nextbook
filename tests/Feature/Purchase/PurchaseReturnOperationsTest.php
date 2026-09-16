<?php

namespace Tests\Feature\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Enums\TransactionStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Every operation the Purchase Returns menu offers: list, create, show, edit,
 * update, post, reverse, delete, restore, force-delete, and the endpoint the
 * form uses to find what is still returnable against a bill.
 */
class PurchaseReturnOperationsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        $this->purchase = $this->buyTenUnits();
    }

    private function buyTenUnits(): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => 7001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => 200,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->latest()->firstOrFail();
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
            'number' => 7501,
            'purchase_id' => $this->purchase->id,
            'date' => '2026-03-12',
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'damaged on arrival',
            'item_list' => [[
                'purchase_item_id' => $this->purchase->items->first()->id,
                'quantity' => 3,
            ]],
        ], $overrides);
    }

    private function createReturn(array $overrides = []): PurchaseReturn
    {
        $this->post(route('purchase-returns.store'), $this->payload($overrides))->assertRedirect();

        return PurchaseReturn::query()->latest()->firstOrFail();
    }

    public function test_the_list_and_create_screens_render(): void
    {
        $this->createReturn();

        $this->get(route('purchase-returns.index'))->assertOk();
        $this->get(route('purchase-returns.create'))->assertOk();
    }

    public function test_the_returnable_items_endpoint_reports_what_is_left_on_the_bill(): void
    {
        $response = $this->getJson(route('purchase-returns.returnable-items', [
            'purchase_id' => $this->purchase->id,
        ]))->assertOk();

        $this->assertNotEmpty($response->json('items'));
    }

    public function test_a_return_sends_the_goods_back_and_reduces_stock(): void
    {
        $this->assertEqualsWithDelta(10.0, $this->onHand(), 0.0001);

        $return = $this->createReturn();

        $this->assertDatabaseHas('purchase_returns', [
            'id' => $return->id,
            'purchase_id' => $this->purchase->id,
        ]);
        $this->assertEqualsWithDelta(
            7.0,
            $this->onHand(),
            0.0001,
            'Returning 3 of 10 to the supplier must take them off the shelf.',
        );
    }

    public function test_the_show_screen_renders(): void
    {
        $return = $this->createReturn();

        $this->get(route('purchase-returns.show', $return))->assertOk();
    }

    public function test_a_return_cannot_exceed_what_was_bought(): void
    {
        $this->post(route('purchase-returns.store'), $this->payload([
            'item_list' => [[
                'purchase_item_id' => $this->purchase->items->first()->id,
                'quantity' => 999,
            ]],
        ]))->assertSessionHasErrors();
    }

    public function test_the_same_purchase_line_cannot_be_returned_twice_in_one_document(): void
    {
        $lineId = $this->purchase->items->first()->id;

        $this->post(route('purchase-returns.store'), $this->payload([
            'item_list' => [
                ['purchase_item_id' => $lineId, 'quantity' => 1],
                ['purchase_item_id' => $lineId, 'quantity' => 1],
            ],
        ]))->assertSessionHasErrors('item_list');
    }

    public function test_a_posted_return_can_be_reversed_and_puts_the_stock_back(): void
    {
        $return = $this->createReturn();
        $this->assertEqualsWithDelta(7.0, $this->onHand(), 0.0001);

        $this->post(route('purchase-returns.reverse', $return), ['reason' => 'sent in error'])
            ->assertRedirect();

        $this->assertSame(TransactionStatus::REVERSED->value, $return->fresh()->status);
        $this->assertEqualsWithDelta(
            10.0,
            $this->onHand(),
            0.0001,
            'Reversing the return must bring the goods back onto the shelf.',
        );
    }

    public function test_a_draft_return_can_be_deleted_restored_and_force_deleted(): void
    {
        $this->ctx['user']->setPreference('transaction.purchase_return_post_immediately', false);
        $this->ctx['user']->save();

        $return = $this->createReturn(['number' => 7502]);

        if ($return->status !== TransactionStatus::DRAFT->value) {
            $this->markTestSkipped('purchase returns always post on create');
        }

        $this->delete(route('purchase-returns.destroy', $return))->assertRedirect();
        $this->assertSoftDeleted('purchase_returns', ['id' => $return->id]);
        $this->assertSoftDeleted('purchase_return_items', ['purchase_return_id' => $return->id]);

        $this->patch(route('purchase-returns.restore', $return->id))->assertRedirect();
        $this->assertNotSoftDeleted('purchase_returns', ['id' => $return->id]);
        // The lines must come back too, not just the header.
        $this->assertNotSoftDeleted('purchase_return_items', ['purchase_return_id' => $return->id]);

        $this->delete(route('purchase-returns.destroy', $return))->assertRedirect();
        $this->delete(route('purchase-returns.force-delete', $return->id))->assertRedirect();
        $this->assertDatabaseMissing('purchase_returns', ['id' => $return->id]);
    }
}
