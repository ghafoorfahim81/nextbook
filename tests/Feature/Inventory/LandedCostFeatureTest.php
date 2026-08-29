<?php

namespace Tests\Feature\Inventory;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Models\Inventory\Item;
use App\Models\Inventory\LandedCost;
use App\Models\Inventory\StockMovement;
use App\Models\Purchase\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class LandedCostFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_it_allocates_landed_cost_to_purchase_stock_movement_and_updates_average_cost(): void
    {
        $purchase = $this->createPostedPurchase([
            [
                'item_id' => $this->ctx['item']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
                'quantity' => 10,
                'unit_price' => 30,
            ],
        ]);

        $purchaseItem = $purchase->items()->firstOrFail();

        $storeResponse = $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::ByValue->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
            ]],
        ));

        $storeResponse->assertRedirect(route('landed-costs.index'));

        $landedCost = LandedCost::query()->latest()->firstOrFail();
        $this->assertEquals(50, (float) $landedCost->allocated_total);
        $this->assertEquals(50, (float) $landedCost->items()->firstOrFail()->allocated_amount);

        $postResponse = $this->postJson('/api/landed-costs/'.$landedCost->id.'/post');
        $postResponse->assertOk();

        $this->assertEquals(LandedCostStatus::Posted, $landedCost->fresh()->status);

        $movement = StockMovement::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->where('item_id', $this->ctx['item']->id)
            ->where('movement_type', StockMovementType::IN)
            ->where('source', StockSourceType::PURCHASE)
            ->firstOrFail();

        $this->assertEquals(35.0, (float) $movement->unit_cost);
        $this->assertEquals(35.0, (float) $this->ctx['item']->fresh()->avg_cost);
    }

    public function test_it_stores_manual_allocation_amounts_and_posts_them(): void
    {
        $secondItem = Item::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'size_id' => $this->ctx['size']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'name' => 'Second Test Item',
            'code' => 'ITEM-002',
            'sku' => 'SKU-002',
        ]);

        $purchase = $this->createPostedPurchase([
            [
                'item_id' => $this->ctx['item']->id,
                'batch' => 'BT-A',
                'expire_date' => '2027-03-01',
                'quantity' => 10,
                'unit_price' => 20,
            ],
            [
                'item_id' => $secondItem->id,
                'batch' => 'BT-B',
                'expire_date' => '2027-04-01',
                'quantity' => 5,
                'unit_price' => 40,
            ],
        ]);

        $items = $purchase->items()->orderBy('created_at')->get();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::Manual->value,
            items: [
                [
                    'purchase_id' => $purchase->id,
                    'purchase_item_id' => $items[0]->id,
                    'item_id' => $this->ctx['item']->id,
                    'quantity' => 10,
                    'unit_cost' => 20,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                    'batch' => 'BT-A',
                    'expire_date' => '2027-03-01',
                    'allocated_amount' => 30,
                ],
                [
                    'purchase_id' => $purchase->id,
                    'purchase_item_id' => $items[1]->id,
                    'item_id' => $secondItem->id,
                    'quantity' => 5,
                    'unit_cost' => 40,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                    'batch' => 'BT-B',
                    'expire_date' => '2027-04-01',
                    'allocated_amount' => 20,
                ],
            ],
        ))->assertRedirect(route('landed-costs.index'));

        $landedCost = LandedCost::query()->latest()->firstOrFail();
        $this->assertEquals(50, (float) $landedCost->allocated_total);
        $this->assertEqualsCanonicalizing(
            [30.0, 20.0],
            $landedCost->items()->pluck('allocated_amount')->map(fn ($value) => (float) $value)->all()
        );

        // The capitalised cost columns must survive the round trip through
        // syncItems() — they are what the show page divides by quantity to get
        // the landed unit cost, and a dropped value renders it as 0.
        $this->assertEqualsCanonicalizing(
            [200.0, 200.0],
            $landedCost->items()->pluck('item_cost_before')->map(fn ($value) => (float) $value)->all()
        );
        $this->assertEqualsCanonicalizing(
            [230.0, 220.0],
            $landedCost->items()->pluck('item_cost_after')->map(fn ($value) => (float) $value)->all()
        );

        $this->getJson('/api/landed-costs/'.$landedCost->id)
            ->assertOk()
            ->assertJsonPath('data.items.0.landed_unit_cost', fn ($value) => (float) $value === 23.0);

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $firstMovement = StockMovement::query()
            ->where('reference_id', $purchase->id)
            ->where('item_id', $this->ctx['item']->id)
            ->firstOrFail();
        $secondMovement = StockMovement::query()
            ->where('reference_id', $purchase->id)
            ->where('item_id', $secondItem->id)
            ->firstOrFail();

        $this->assertEquals(23.0, (float) $firstMovement->unit_cost);
        $this->assertEquals(44.0, (float) $secondMovement->unit_cost);
        $this->assertEquals(23.0, (float) $this->ctx['item']->fresh()->avg_cost);
        $this->assertEquals(44.0, (float) $secondItem->fresh()->avg_cost);
    }

    public function test_it_rejects_posting_when_manual_allocation_does_not_match_additional_cost(): void
    {
        $purchase = $this->createPostedPurchase([
            [
                'item_id' => $this->ctx['item']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
                'quantity' => 10,
                'unit_price' => 30,
            ],
        ]);

        $purchaseItem = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::Manual->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
                'allocated_amount' => 10,
            ]],
        ))->assertRedirect(route('landed-costs.index'));

        $landedCost = LandedCost::query()->latest()->firstOrFail();

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['allocated_total']);

        $this->assertNotEquals(LandedCostStatus::Posted, $landedCost->fresh()->status);
    }

    private function createPostedPurchase(array $lines): Purchase
    {
        $itemList = array_map(fn (array $line) => [
            'item_id' => $line['item_id'],
            'batch' => $line['batch'] ?? null,
            'expire_date' => $line['expire_date'] ?? null,
            'quantity' => $line['quantity'],
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'unit_price' => $line['unit_price'],
            'item_discount' => 0,
            'free' => 0,
            'tax' => 0,
        ], $lines);

        $this->post(route('purchases.store'), [
            'number' => fake()->unique()->numberBetween(6000, 9000),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => collect($itemList)->sum(fn ($line) => $line['quantity'] * $line['unit_price']),
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'discount' => 0,
            'discount_type' => 'percentage',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'landed cost purchase',
            'item_list' => $itemList,
        ])->assertRedirect(route('purchases.index'));

        return Purchase::query()->latest()->firstOrFail();
    }

    public function test_it_rejects_a_purchase_order_that_already_carries_a_landed_cost(): void
    {
        $purchase = $this->createPostedPurchase([
            [
                'item_id' => $this->ctx['item']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
                'quantity' => 10,
                'unit_price' => 30,
            ],
        ]);

        $purchaseItem = $purchase->items()->firstOrFail();

        $payload = $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::Manual->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
                'allocated_amount' => 50,
            ]],
        );

        $this->post(route('landed-costs.store'), $payload)->assertRedirect(route('landed-costs.index'));

        // Second landed cost on the same purchase order: rejected, and the
        // purchase order no longer appears among the form's options.
        $this->post(route('landed-costs.store'), $payload)->assertSessionHasErrors('purchase_ids');

        $this->assertSame(1, LandedCost::query()->count());

        $landedCost = LandedCost::query()->latest()->firstOrFail();

        $this->get(route('landed-costs.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'purchases',
                fn ($purchases) => collect($purchases)->doesntContain('id', $purchase->id)
            ));

        // Editing the landed cost that owns it keeps it selectable.
        $this->get(route('landed-costs.edit', $landedCost->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'purchases',
                fn ($purchases) => collect($purchases)->contains('id', $purchase->id)
            ));
    }

    private function landedCostPayload(Purchase $purchase, float $totalCost, string $method, array $items): array
    {
        return [
            'date' => '2026-03-20',
            'purchase_id' => $purchase->id,
            'purchase_ids' => [$purchase->id],
            // The funding side of the landed cost: its draft transaction is
            // built at save time, so the account/currency/rate travel with
            // every store and update request.
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'total_cost' => $totalCost,
            'allocation_method' => $method,
            'notes' => 'freight and customs',
            'items' => $items,
        ];
    }
}
