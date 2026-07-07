<?php

namespace Tests\Feature\Sale;

use App\Enums\SaleOrderStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleOrder;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class SaleOrderTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function storeSaleOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 5001,
            'date' => '2026-03-19',
            'delivery_date' => '2026-03-25',
            'customer_id' => $this->ctx['customer_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'discount' => 0,
            'note' => 'test sale order',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'quantity' => 3,
                    'free' => 0,
                    'unit_price' => 50,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'size_id' => null,
                    'category_id' => null,
                    'discount' => 0,
                ],
            ],
        ], $overrides);
    }

    public function test_sale_order_can_be_created_and_defaults_to_posted(): void
    {
        $response = $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload());

        $response->assertRedirect(route('sale-orders.index'));

        $this->assertDatabaseHas('sale_orders', [
            'number' => 5001,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'status' => SaleOrderStatus::POSTED->value,
        ]);

        $saleOrder = SaleOrder::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('sale_order_items', [
            'sale_order_id' => $saleOrder->id,
            'item_id' => $this->ctx['item']->id,
            'quantity' => 3.00,
            'unit_price' => 50.0000,
        ]);
    }

    public function test_eligible_endpoint_lists_posted_orders_for_customer(): void
    {
        $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload());

        $response = $this->getJson(route('sale-orders.eligible', ['customer_id' => $this->ctx['customer_ledger']->id]));

        $response->assertOk();
        $response->assertJsonCount(1, 'sale_orders');
        $response->assertJsonPath('sale_orders.0.number', 5001);
    }

    public function test_for_conversion_endpoint_returns_header_and_items(): void
    {
        $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload());
        $saleOrder = SaleOrder::query()->latest()->firstOrFail();

        $response = $this->getJson(route('sale-orders.for-conversion', $saleOrder->id));

        $response->assertOk();
        $response->assertJsonPath('sale_order.customer_id', $this->ctx['customer_ledger']->id);
        $response->assertJsonCount(1, 'items');
        $this->assertEquals(3, $response->json('items.0.quantity'));
    }

    public function test_sale_creation_from_posted_sale_order_completes_the_order(): void
    {
        $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload());
        $saleOrder = SaleOrder::query()->latest()->firstOrFail();

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 10,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-sale-order-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $response = $this->post(route('sales.store'), [
            'number' => 9501,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'sale_order_id' => $saleOrder->id,
            'date' => '2026-03-20',
            'transaction_total' => 150,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 3,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 50,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));

        $sale = Sale::query()->latest()->firstOrFail();
        $this->assertEquals($saleOrder->id, $sale->sale_order_id);

        $this->assertDatabaseHas('sale_orders', [
            'id' => $saleOrder->id,
            'status' => SaleOrderStatus::COMPLETED->value,
        ]);
    }

    public function test_sale_creation_rejects_a_non_posted_sale_order(): void
    {
        $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload());
        $saleOrder = SaleOrder::query()->latest()->firstOrFail();
        $saleOrder->update(['status' => SaleOrderStatus::CANCELLED->value]);

        $response = $this->post(route('sales.store'), [
            'number' => 9502,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'sale_order_id' => $saleOrder->id,
            'date' => '2026-03-20',
            'transaction_total' => 150,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 3,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 50,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('sales', ['number' => 9502]);
    }

    public function test_draft_sale_order_can_be_posted_then_no_longer_edited_or_cancelled(): void
    {
        // Force draft by disabling post-immediately preference for this request.
        $this->ctx['user']->setPreference('transaction.sale_order_post_immediately', false);
        $this->ctx['user']->save();

        $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload(['number' => 5002]));
        $saleOrder = SaleOrder::query()->where('number', 5002)->firstOrFail();
        $this->assertEquals(SaleOrderStatus::DRAFT->value, $saleOrder->status);

        $this->post(route('sale-orders.post', $saleOrder->id))->assertRedirect();
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => SaleOrderStatus::POSTED->value]);

        // Once posted, the order can no longer be cancelled or edited.
        $this->post(route('sale-orders.cancel', $saleOrder->id))->assertStatus(422);
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => SaleOrderStatus::POSTED->value]);

        $this->get(route('sale-orders.edit', $saleOrder->id))->assertRedirect();
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => SaleOrderStatus::POSTED->value]);
    }

    public function test_draft_sale_order_can_be_cancelled(): void
    {
        $this->ctx['user']->setPreference('transaction.sale_order_post_immediately', false);
        $this->ctx['user']->save();

        $this->post(route('sale-orders.store'), $this->storeSaleOrderPayload(['number' => 5003]));
        $saleOrder = SaleOrder::query()->where('number', 5003)->firstOrFail();
        $this->assertEquals(SaleOrderStatus::DRAFT->value, $saleOrder->status);

        $this->post(route('sale-orders.cancel', $saleOrder->id))->assertRedirect();
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => SaleOrderStatus::CANCELLED->value]);
    }
}
