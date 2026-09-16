<?php

namespace Tests\Feature\Purchase;

use App\Enums\PurchaseOrderStatus;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Every operation the Purchase Orders menu offers, end to end: list, create,
 * show, edit, update, post, cancel, delete, restore, force-delete, and the two
 * endpoints the Purchase form uses to pull an order in.
 */
class PurchaseOrderOperationsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => 6001,
            'date' => '2026-03-19',
            'delivery_date' => '2026-03-25',
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'discount' => 0,
            'note' => 'purchase order operations test',
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'quantity' => 4,
                'free' => 0,
                'unit_price' => 25,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'batch' => null,
                'expire_date' => null,
                'category_id' => null,
                'discount' => 0,
            ]],
        ], $overrides);
    }

    private function createOrder(array $overrides = []): PurchaseOrder
    {
        $this->post(route('purchase-orders.store'), $this->payload($overrides))->assertRedirect();

        return PurchaseOrder::query()->latest()->firstOrFail();
    }

    /**
     * Orders post on create by default, and a posted order is immutable — it
     * can only be converted into a purchase. Everything that edits, cancels or
     * deletes therefore has to start from a draft.
     */
    private function createDraftOrder(array $overrides = []): PurchaseOrder
    {
        $this->ctx['user']->setPreference('transaction.purchase_order_post_immediately', false);
        $this->ctx['user']->save();

        $order = $this->createOrder($overrides);

        $this->assertSame(PurchaseOrderStatus::DRAFT->value, $order->status);

        return $order;
    }

    public function test_the_list_and_create_screens_render(): void
    {
        $this->createOrder();

        $this->get(route('purchase-orders.index'))->assertOk();
        $this->get(route('purchase-orders.create'))->assertOk();
    }

    public function test_an_order_can_be_created_with_its_lines(): void
    {
        $order = $this->createOrder();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $order->id,
            'number' => 6001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'item_id' => $this->ctx['item']->id,
            'quantity' => 4.00,
            'unit_price' => 25.0000,
        ]);
    }

    public function test_a_posted_order_can_be_viewed_but_not_edited(): void
    {
        $order = $this->createOrder();

        $this->get(route('purchase-orders.show', $order))->assertOk();
        // Posted is immutable: the edit screen turns the user away.
        $this->get(route('purchase-orders.edit', $order))->assertRedirect();
    }

    public function test_a_draft_order_edit_screen_renders(): void
    {
        $order = $this->createDraftOrder();

        $this->get(route('purchase-orders.edit', $order))->assertOk();
    }

    public function test_a_draft_order_can_be_updated(): void
    {
        $order = $this->createDraftOrder();

        $this->put(route('purchase-orders.update', $order), $this->payload([
            'number' => 6001,
            'note' => 'amended note',
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'quantity' => 7,
                'free' => 0,
                'unit_price' => 30,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'batch' => null,
                'expire_date' => null,
                'category_id' => null,
                'discount' => 0,
            ]],
        ]))->assertRedirect();

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $order->id,
            'quantity' => 7.00,
            'unit_price' => 30.0000,
        ]);
    }

    public function test_a_draft_order_can_be_cancelled_and_a_posted_one_cannot(): void
    {
        $order = $this->createDraftOrder();

        $this->post(route('purchase-orders.cancel', $order))->assertRedirect();

        $this->assertSame(
            PurchaseOrderStatus::CANCELLED->value,
            $order->fresh()->status,
        );
    }

    public function test_the_eligible_endpoint_lists_posted_orders_for_the_supplier(): void
    {
        $this->createOrder();

        $this->getJson(route('purchase-orders.eligible', [
            'supplier_id' => $this->ctx['supplier_ledger']->id,
        ]))->assertOk()->assertJsonPath('purchase_orders.0.number', 6001);
    }

    public function test_the_for_conversion_endpoint_returns_the_header_and_lines(): void
    {
        $order = $this->createOrder();

        $response = $this->getJson(route('purchase-orders.for-conversion', $order))->assertOk();

        $this->assertSame($this->ctx['supplier_ledger']->id, $response->json('purchase_order.supplier_id'));
        $this->assertCount(1, $response->json('items'));
        $this->assertSame($this->ctx['item']->id, $response->json('items.0.item_id'));
    }

    /** The whole point of an order: it becomes a purchase. */
    public function test_an_order_can_be_pulled_into_a_purchase_and_is_completed_by_it(): void
    {
        $order = $this->createOrder();

        $this->post(route('purchases.store'), [
            'number' => 6101,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-20',
            'transaction_total' => 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'purchase_order_id' => $order->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 4,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 25,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        $purchase = Purchase::query()->latest()->firstOrFail();

        $this->assertSame($order->id, $purchase->purchase_order_id);
        $this->assertSame(PurchaseOrderStatus::COMPLETED->value, $order->fresh()->status);
    }

    public function test_a_draft_order_can_be_deleted_restored_and_force_deleted(): void
    {
        $order = $this->createDraftOrder();

        $this->delete(route('purchase-orders.destroy', $order))->assertRedirect();
        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
        $this->assertSoftDeleted('purchase_order_items', ['purchase_order_id' => $order->id]);

        $this->patch(route('purchase-orders.restore', $order->id))->assertRedirect();
        $this->assertNotSoftDeleted('purchase_orders', ['id' => $order->id]);
        // The lines have to come back with it. They did not before: the
        // soft-delete check in DeletedRecordService silently skipped every
        // child, so an order came back as an empty shell.
        $this->assertNotSoftDeleted('purchase_order_items', ['purchase_order_id' => $order->id]);

        $this->delete(route('purchase-orders.destroy', $order))->assertRedirect();
        $this->delete(route('purchase-orders.force-delete', $order->id))->assertRedirect();
        $this->assertDatabaseMissing('purchase_orders', ['id' => $order->id]);
    }
}
