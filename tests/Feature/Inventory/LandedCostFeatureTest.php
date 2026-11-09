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
            'company_id' => $this->ctx['company']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
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

    /**
     * A manual allocation that does not add up is now refused at the door,
     * rather than being saved and only failing later at post time. The service
     * still re-checks before posting (assertAllocationMatchesTotalCost), so the
     * guarantee is unchanged — it just fails earlier, where the user can see
     * which figure is wrong while they are still editing it.
     */
    public function test_it_rejects_a_manual_allocation_that_does_not_match_the_additional_cost(): void
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
                // 10 allocated against a 50 total cost.
                'allocated_amount' => 10,
            ]],
        ))->assertSessionHasErrors('items');

        $this->assertDatabaseCount('landed_costs', 0);
    }

    /** The proportional methods let the server do the split, and it adds up. */
    public function test_a_proportional_method_does_not_need_the_client_to_send_an_allocation(): void
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
            method: LandedCostAllocationMethod::ByQuantity->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-100',
                'expire_date' => '2027-03-01',
                // No allocated_amount: by_quantity is the server's to compute.
            ]],
        ))->assertRedirect(route('landed-costs.index'));

        $landedCost = LandedCost::query()->latest()->firstOrFail();

        $this->assertEquals(50, (float) $landedCost->allocated_total);
    }

    private function createPostedPurchase(array $lines): Purchase
    {
        $itemList = array_map(fn (array $line) => [
            'item_id' => $line['item_id'],
            'batch' => $line['batch'] ?? null,
            'expire_date' => $line['expire_date'] ?? null,
            'variant_id' => $line['variant_id'] ?? null,
            'quantity' => $line['quantity'],
            'unit_measure_id' => $line['unit_measure_id'] ?? $this->ctx['unit_measure']->id,
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

    public function test_a_posted_landed_cost_can_be_reversed_and_gives_the_cost_back(): void
    {
        $purchase = $this->createPostedPurchase([[
            'item_id' => $this->ctx['item']->id,
            'batch' => 'BT-R',
            'expire_date' => '2027-03-01',
            'quantity' => 10,
            'unit_price' => 30,
        ]]);

        $landedCost = $this->storeLandedCost($purchase, 50);
        $this->assertNotEmpty($landedCost->number);

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();
        $this->assertEquals(35.0, (float) $this->purchaseMovement($purchase)->unit_cost);

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertOk();

        $this->assertEquals(LandedCostStatus::Reversed, $landedCost->fresh()->status);
        $this->assertEquals(30.0, (float) $this->purchaseMovement($purchase)->unit_cost);
        $this->assertEquals(30.0, (float) $this->ctx['item']->fresh()->avg_cost);

        // A reversed record is final: it cannot be edited, deleted or reversed twice.
        $this->delete(route('landed-costs.destroy', $landedCost->id))->assertSessionHasErrors('landed_cost');
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertStatus(422);
    }

    public function test_a_draft_cannot_be_reversed(): void
    {
        $purchase = $this->createPostedPurchase([[
            'item_id' => $this->ctx['item']->id,
            'batch' => 'BT-D',
            'expire_date' => '2027-03-01',
            'quantity' => 10,
            'unit_price' => 30,
        ]]);

        $landedCost = $this->storeLandedCost($purchase, 50);

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertStatus(422);
    }

    public function test_a_foreign_currency_cost_reaches_stock_at_its_rate(): void
    {
        $foreign = \App\Models\Administration\Currency::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'is_base_currency' => false,
        ]);

        $purchase = $this->createPostedPurchase([[
            'item_id' => $this->ctx['item']->id,
            'batch' => 'BT-F',
            'expire_date' => '2027-03-01',
            'quantity' => 10,
            'unit_price' => 30,
        ]]);

        // 10 units of foreign currency at 70 home per unit = 700 home over 10 pieces.
        $landedCost = $this->storeLandedCost($purchase, 10, ['currency_id' => $foreign->id, 'rate' => 70]);
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $this->assertEquals(100.0, (float) $this->purchaseMovement($purchase)->unit_cost);
    }

    private function purchaseWithOneItem(string $batch): Purchase
    {
        return $this->createPostedPurchase([[
            'item_id' => $this->ctx['item']->id,
            'batch' => $batch,
            'expire_date' => '2027-03-01',
            'quantity' => 10,
            'unit_price' => 30,
        ]]);
    }

    public function test_a_by_weight_split_is_refused_when_an_item_has_no_weight(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-W0');
        $this->ctx['item']->update(['weight' => null]);
        $purchaseItem = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::ByWeight->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-W0',
                'expire_date' => '2027-03-01',
            ]],
        ))->assertSessionHasErrors('allocation_method');

        $this->assertSame(0, LandedCost::query()->count());
    }

    public function test_a_by_weight_split_uses_the_items_weight(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-W1');
        $this->ctx['item']->update(['weight' => 2]);

        $landedCost = $this->storeLandedCost($purchase, 50, ['allocation_method' => LandedCostAllocationMethod::ByWeight->value]);

        $this->assertEquals(20.0, (float) $landedCost->items()->firstOrFail()->weight);
        $this->assertEquals(50.0, (float) $landedCost->allocated_total);
    }

    public function test_a_by_volume_split_is_refused_when_an_item_has_no_dimensions(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-V0');
        $this->ctx['item']->update(['length' => null, 'width' => null, 'height' => null]);
        $purchaseItem = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::ByVolume->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-V0',
                'expire_date' => '2027-03-01',
            ]],
        ))->assertSessionHasErrors('allocation_method');
    }

    public function test_a_purchase_that_is_not_posted_cannot_carry_a_landed_cost(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-NP');
        Purchase::query()->whereKey($purchase->id)->update(['status' => 'draft']);
        $purchaseItem = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
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
                'batch' => 'BT-NP',
                'expire_date' => '2027-03-01',
            ]],
        ))->assertSessionHasErrors('purchase_ids');
    }

    public function test_a_reversed_landed_cost_frees_its_purchase_for_a_new_one(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-RF');

        $first = $this->storeLandedCost($purchase, 50);
        $this->postJson('/api/landed-costs/'.$first->id.'/post')->assertOk();
        $this->postJson('/api/landed-costs/'.$first->id.'/reverse')->assertOk();

        // storeLandedCost() redirecting to the index is the proof the store was
        // accepted; the second record is looked up by exclusion because two
        // records created in the same second tie on `latest()`.
        $this->storeLandedCost($purchase, 20);

        $second = LandedCost::query()->whereKeyNot($first->id)->firstOrFail();
        $this->assertEquals(20.0, (float) $second->total_cost);
        $this->assertEquals(2, $purchase->landedCosts()->count());
    }

    public function test_a_reversed_landed_cost_cannot_be_posted_again(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-RP');

        $landedCost = $this->storeLandedCost($purchase, 50);
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertOk();

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertStatus(422);
        $this->assertEquals(30.0, (float) $this->purchaseMovement($purchase)->unit_cost);
    }

    public function test_a_row_from_a_purchase_that_was_not_selected_is_refused(): void
    {
        $selected = $this->purchaseWithOneItem('BT-S1');
        $this->purchaseWithOneItem('BT-S2');

        // createPostedPurchase() returns `latest()`, which ties for two bills
        // made in the same second — so the second one is found by exclusion.
        $other = Purchase::query()->whereKeyNot($selected->id)->firstOrFail();
        $otherLine = $other->items()->firstOrFail();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $selected,
            totalCost: 50,
            method: LandedCostAllocationMethod::ByValue->value,
            items: [[
                'purchase_id' => $other->id,
                'purchase_item_id' => $otherLine->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-S2',
                'expire_date' => '2027-03-01',
            ]],
        ))->assertSessionHasErrors('items');

        $this->assertSame(0, LandedCost::query()->count());
    }

    public function test_quantity_and_cost_come_from_the_purchase_line_not_the_request(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-Q');
        $line = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::ByValue->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $line->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 999,
                'unit_cost' => 1,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-Q',
                'expire_date' => '2027-03-01',
            ]],
        ))->assertRedirect(route('landed-costs.index'));

        $row = LandedCost::query()->latest()->firstOrFail()->items()->firstOrFail();

        $this->assertEquals(10.0, (float) $row->quantity);
        $this->assertEquals(30.0, (float) $row->unit_cost);
    }

    public function test_reallocating_rebuilds_the_voucher_so_the_gl_matches_the_new_total(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-AL');
        $landedCost = $this->storeLandedCost($purchase, 50);
        $line = $purchase->items()->firstOrFail();

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/allocate', $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 80,
            method: LandedCostAllocationMethod::ByValue->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $line->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => 'BT-AL',
                'expire_date' => '2027-03-01',
            ]],
        ))->assertOk();

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $this->assertEquals(38.0, (float) $this->purchaseMovement($purchase)->unit_cost);
        $this->assertEquals(80.0, (float) $landedCost->fresh()->transaction->lines()->sum('debit'));
    }

    private function sellFromStock(float $quantity): void
    {
        $this->post(route('sales.store'), [
            'number' => 9001,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-25',
            'transaction_total' => $quantity * 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 100,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();
    }

    public function test_a_landed_cost_cannot_be_posted_once_part_of_the_stock_is_sold(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-SOLD');
        $landedCost = $this->storeLandedCost($purchase, 50);

        $this->sellFromStock(2);

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')
            ->assertStatus(422)
            ->assertJsonValidationErrors('landed_cost');

        $this->assertEquals(LandedCostStatus::Draft, $landedCost->fresh()->status);
        $this->assertEquals(30.0, (float) $this->purchaseMovement($purchase)->unit_cost);
    }

    public function test_a_posted_landed_cost_cannot_be_reversed_after_the_stock_is_sold(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-SOLD2');
        $landedCost = $this->storeLandedCost($purchase, 50);
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $this->sellFromStock(2);

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')
            ->assertStatus(422)
            ->assertJsonValidationErrors('landed_cost');

        $this->assertEquals(LandedCostStatus::Posted, $landedCost->fresh()->status);
        $this->assertEquals(35.0, (float) $this->purchaseMovement($purchase)->unit_cost);
    }

    public function test_the_paying_account_must_be_cash_or_bank(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-ACC');
        $line = $purchase->items()->firstOrFail();

        $payload = $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 50,
            method: LandedCostAllocationMethod::ByValue->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $line->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => 10,
                'unit_cost' => 30,
            ]],
        );
        $payload['bank_account_id'] = $this->ctx['accounts']['inventory-stock']->id;

        $this->post(route('landed-costs.store'), $payload)->assertSessionHasErrors('bank_account_id');
    }

    public function test_a_deleted_landed_cost_cannot_be_restored_onto_a_bill_that_took_another(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-TR');

        $first = $this->storeLandedCost($purchase, 50);
        $this->delete(route('landed-costs.destroy', $first->id))->assertRedirect();

        $this->storeLandedCost($purchase, 20);

        try {
            app(\App\Services\DeletedRecordService::class)->restore('landed_costs', $first->id);
            $this->fail('The restore should have been refused.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('record', $e->errors());
        }

        $this->assertTrue(LandedCost::withTrashed()->findOrFail($first->id)->trashed());
    }

    public function test_a_deleted_landed_cost_comes_back_while_its_bill_is_free(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-TR2');

        $landedCost = $this->storeLandedCost($purchase, 50);
        $this->delete(route('landed-costs.destroy', $landedCost->id))->assertRedirect();

        app(\App\Services\DeletedRecordService::class)->restore('landed_costs', $landedCost->id);

        $restored = LandedCost::query()->findOrFail($landedCost->id);
        $this->assertSame([$purchase->id], $restored->purchases()->pluck('purchases.id')->all());
    }

    public function test_reversing_needs_its_own_permission_not_the_post_one(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-PERM');
        $landedCost = $this->storeLandedCost($purchase, 50);
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $this->actingAs($this->userWithPermissions(['landed_costs.post']));
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertForbidden();

        $this->actingAs($this->userWithPermissions(['landed_costs.reverse']));
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertOk();
    }

    private function userWithPermissions(array $permissions): \App\Models\User
    {
        $key = md5(implode(',', $permissions));

        $role = \App\Models\Role::query()->firstOrCreate(
            ['name' => 'limited-'.$key, 'guard_name' => 'web'],
            ['slug' => 'limited-'.$key],
        );

        foreach ($permissions as $name) {
            $role->givePermissionTo(\App\Models\Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['id' => (string) new \Symfony\Component\Uid\Ulid()],
            ));
        }

        return tap(\App\Models\User::factory()->create([
            'preferences' => \App\Support\Settings::defaults(),
            'company_id' => $this->ctx['company']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]), fn (\App\Models\User $user) => $user->assignRole($role));
    }

    /**
     * One bill with 2 boxes of 12 of the first item and 24 pieces of a second:
     * the same number of pieces, so any split by pieces is half and half.
     */
    private function billOfBoxesAndPieces(): array
    {
        $box = \App\Models\Administration\UnitMeasure::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'quantity_id' => $this->ctx['quantity']->id,
            'name' => 'Box',
            'unit' => '12',
            'symbol' => 'bx',
            'is_main' => false,
            'is_active' => true,
        ]);

        $second = $this->ctx['item']->replicate(['avg_cost']);
        $second->name = 'Second item';
        $second->code = 'SECOND-'.fake()->unique()->numberBetween(1000, 9999);
        $second->sku = $second->code;
        $second->barcode = null;
        $second->save();

        $purchase = $this->createPostedPurchase([
            ['item_id' => $this->ctx['item']->id, 'quantity' => 2, 'unit_measure_id' => $box->id, 'unit_price' => 120],
            ['item_id' => $second->id, 'quantity' => 24, 'unit_price' => 10],
        ]);

        $rows = $purchase->items()->get()->map(fn ($line) => [
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $line->id,
            'item_id' => $line->item_id,
            'quantity' => (float) $line->quantity,
            'unit_cost' => (float) $line->unit_price,
        ])->all();

        return [$purchase, $rows, $second];
    }

    public function test_a_split_by_quantity_counts_boxes_in_the_items_own_unit(): void
    {
        [$purchase, $rows] = $this->billOfBoxesAndPieces();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 100,
            method: LandedCostAllocationMethod::ByQuantity->value,
            items: $rows,
        ))->assertRedirect(route('landed-costs.index'));

        $amounts = LandedCost::query()->firstOrFail()->items()->pluck('allocated_amount')->map(fn ($a) => (float) $a)->all();

        $this->assertEquals([50.0, 50.0], $amounts);
    }

    public function test_a_split_by_weight_counts_boxes_in_the_items_own_unit(): void
    {
        [$purchase, $rows, $second] = $this->billOfBoxesAndPieces();
        $this->ctx['item']->update(['weight' => 2]);
        $second->update(['weight' => 2]);

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 100,
            method: LandedCostAllocationMethod::ByWeight->value,
            items: $rows,
        ))->assertRedirect(route('landed-costs.index'));

        $saved = LandedCost::query()->firstOrFail()->items()->get();

        $this->assertEquals([48.0, 48.0], $saved->pluck('weight')->map(fn ($w) => (float) $w)->all());
        $this->assertEquals([50.0, 50.0], $saved->pluck('allocated_amount')->map(fn ($a) => (float) $a)->all());
    }

    public function test_the_edit_screen_gets_what_it_needs_to_re_split_a_saved_draft(): void
    {
        [$purchase, $rows, $second] = $this->billOfBoxesAndPieces();
        $this->ctx['item']->update(['weight' => 2, 'length' => 2, 'width' => 3, 'height' => 4]);
        $second->update(['weight' => 2]);

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 100,
            method: LandedCostAllocationMethod::ByWeight->value,
            items: $rows,
        ))->assertRedirect(route('landed-costs.index'));

        $landedCost = LandedCost::query()->firstOrFail();
        $boxLine = $landedCost->items()->where('item_id', $this->ctx['item']->id)->firstOrFail();

        $request = \Illuminate\Http\Request::create(route('landed-costs.edit', $landedCost->id));
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $payload = (new \App\Http\Resources\Inventory\LandedCostItemResource($boxLine->load('item', 'purchaseItem.item')))->resolve($request);

        $this->assertEquals(24.0, $payload['base_quantity']);
        $this->assertEquals(2.0, $payload['item_weight']);
        $this->assertEquals(24.0, $payload['item_volume']);
        $this->assertEquals(48.0, (float) $payload['weight']);

        $this->get(route('landed-costs.edit', $landedCost->id))->assertOk();
    }

    public function test_two_variants_on_one_bill_each_keep_their_own_share(): void
    {
        $this->ctx['item']->update(['has_variants' => true]);

        $variant = fn (string $name, string $sku) => \App\Models\Inventory\ItemVariant::query()->create([
            'company_id' => $this->ctx['company']->id,
            'item_id' => $this->ctx['item']->id,
            'name' => $name,
            'sku' => $sku,
            'barcode' => null,
            'attributes' => ['memory' => $name],
            'variant_key' => $sku,
            'is_default' => false,
            'is_active' => true,
        ]);

        $small = $variant('8GB', 'VAR-8');
        $large = $variant('16GB', 'VAR-16');

        $purchase = $this->createPostedPurchase([
            ['item_id' => $this->ctx['item']->id, 'variant_id' => $small->id, 'quantity' => 10, 'unit_price' => 100],
            ['item_id' => $this->ctx['item']->id, 'variant_id' => $large->id, 'quantity' => 10, 'unit_price' => 300],
        ]);

        $lines = $purchase->items()->get();

        $this->post(route('landed-costs.store'), $this->landedCostPayload(
            purchase: $purchase,
            totalCost: 400,
            method: LandedCostAllocationMethod::ByValue->value,
            items: $lines->map(fn ($line) => [
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $line->id,
                'item_id' => $line->item_id,
                'quantity' => (float) $line->quantity,
                'unit_cost' => (float) $line->unit_price,
            ])->all(),
        ))->assertRedirect(route('landed-costs.index'));

        $landedCost = LandedCost::query()->firstOrFail();
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $cost = fn ($v) => (float) StockMovement::query()
            ->where('reference_id', $purchase->id)
            ->where('variant_id', $v->id)
            ->where('movement_type', StockMovementType::IN)
            ->firstOrFail()
            ->unit_cost;

        // 400 by value is 100 and 300, so 10 and 30 a piece, each on its own variant.
        $this->assertEquals(110.0, $cost($small));
        $this->assertEquals(330.0, $cost($large));

        // The row names its variant; an item with only its default variant shows no name.
        $saved = $landedCost->items()->with('purchaseItem')->get();
        $this->assertEqualsCanonicalizing(['8GB', '16GB'], $saved->map(fn ($row) => $row->purchaseItem->distinctVariantName())->all());

        // Found by exclusion: `latest()` ties for two bills made in the same second.
        $this->purchaseWithOneItem('BT-PLAIN');
        $plain = Purchase::query()->whereKeyNot($purchase->id)->firstOrFail()->items()->firstOrFail();
        $plain->variant_name = $this->ctx['item']->name;
        $this->assertNull($plain->distinctVariantName());
    }

    public function test_attachments_reach_the_details_and_edit_screens(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $purchase = $this->purchaseWithOneItem('BT-ATT');
        $landedCost = $this->storeLandedCost($purchase, 50, [
            'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('customs.pdf', 50, 'application/pdf')],
        ]);

        $this->assertSame(1, $landedCost->attachments()->count());

        foreach (['landed-costs.show', 'landed-costs.edit'] as $route) {
            $this->get(route($route, $landedCost->id))->assertInertia(
                // Inertia wraps a resource collection in `data`; the pages unwrap it
                // in normalizeRecord(), the same as they do for items and purchases.
                fn ($page) => $page->where('landedCost.attachments.data.0.original_name', 'customs.pdf')
            );
        }
    }

    public function test_a_file_can_be_added_when_editing_a_draft(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $purchase = $this->purchaseWithOneItem('BT-ATT2');
        $landedCost = $this->storeLandedCost($purchase, 50);
        $line = $purchase->items()->firstOrFail();

        // The way the form sends it: a POST carrying _method, as multipart.
        $this->post(route('landed-costs.update', $landedCost->id), array_merge(
            $this->landedCostPayload($purchase, 50, LandedCostAllocationMethod::ByValue->value, [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $line->id,
                'item_id' => $line->item_id,
                'quantity' => 10,
                'unit_cost' => 30,
            ]]),
            ['_method' => 'put', 'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('more.pdf', 50, 'application/pdf')]],
        ))->assertRedirect(route('landed-costs.index'));

        $this->assertSame(1, $landedCost->attachments()->count());
    }

    public function test_a_file_of_a_type_that_is_not_allowed_is_refused(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $purchase = $this->purchaseWithOneItem('BT-ATT3');
        $line = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), array_merge(
            $this->landedCostPayload($purchase, 50, LandedCostAllocationMethod::ByValue->value, [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $line->id,
                'item_id' => $line->item_id,
                'quantity' => 10,
                'unit_cost' => 30,
            ]]),
            ['attachments' => [\Illuminate\Http\UploadedFile::fake()->create('run.exe', 50, 'application/octet-stream')]],
        ))->assertSessionHasErrors('attachments.0');
    }

    public function test_the_voucher_carries_a_reference_and_remarks_in_every_language(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-LBL');
        $landedCost = $this->storeLandedCost($purchase, 50);
        $number = $landedCost->number;

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $voucher = $landedCost->fresh()->transaction;
        $this->assertSame('Landed cost #'.$number, $voucher->voucher_number);
        $this->assertSame('Landed cost #'.$number, $voucher->remark);

        foreach ($voucher->lines()->get() as $line) {
            $this->assertStringContainsString('#'.$number, $line->remark);
            $this->assertStringContainsString('مصرف جنس #'.$number, $line->remark_fa);
            $this->assertStringContainsString('#'.$number, $line->remark_ps);
        }

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertOk();

        $reversal = \App\Models\Transaction\Transaction::query()->where('reversal_of_id', $voucher->id)->firstOrFail();
        $this->assertSame('Reversal of Landed cost #'.$number, $reversal->voucher_number);
        $this->assertSame('ابطال مصرف جنس #'.$number, $reversal->lines()->firstOrFail()->remark_fa);
    }

    public function test_the_account_is_named_in_the_users_language(): void
    {
        $purchase = $this->purchaseWithOneItem('BT-NAME');
        $landedCost = $this->storeLandedCost($purchase, 50)->load('bankAccount');
        $landedCost->bankAccount->update(['name' => 'Cash in Hand', 'local_name' => 'نقد در صندوق']);

        $resolve = fn () => (new \App\Http\Resources\Inventory\LandedCostResource($landedCost))->resolve(request())['bank_account_name'];

        app()->setLocale('fa');
        $this->assertSame('نقد در صندوق', $resolve());

        app()->setLocale('en');
        $this->assertSame('Cash in Hand', $resolve());
    }

    public function test_stock_value_matches_the_ledger_when_the_cost_does_not_divide_evenly(): void
    {
        // 100 / 7000 = 0.0142857...: at 4 decimals this was 100.10 of stock.
        $purchase = $this->createPostedPurchase([[
            'item_id' => $this->ctx['item']->id,
            'batch' => 'BT-BIG',
            'expire_date' => '2027-03-01',
            'quantity' => 7000,
            'unit_price' => 30,
        ]]);

        $landedCost = $this->storeLandedCost($purchase, 100);
        $this->postJson('/api/landed-costs/'.$landedCost->id.'/post')->assertOk();

        $movement = $this->purchaseMovement($purchase);
        $this->assertEqualsWithDelta(210100.0, (float) $movement->quantity * (float) $movement->unit_cost, 0.001);
        $this->assertEquals(100.0, (float) $landedCost->fresh()->transaction->lines()->sum('debit'));

        $this->postJson('/api/landed-costs/'.$landedCost->id.'/reverse')->assertOk();

        $this->assertEquals(30.0, (float) $this->purchaseMovement($purchase)->unit_cost);
    }

    private function purchaseMovement(Purchase $purchase): StockMovement
    {
        return StockMovement::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->where('movement_type', StockMovementType::IN)
            ->where('source', StockSourceType::PURCHASE)
            ->firstOrFail();
    }

    private function storeLandedCost(Purchase $purchase, float $totalCost, array $overrides = []): LandedCost
    {
        $purchaseItem = $purchase->items()->firstOrFail();

        $this->post(route('landed-costs.store'), array_merge($this->landedCostPayload(
            purchase: $purchase,
            totalCost: $totalCost,
            method: LandedCostAllocationMethod::ByValue->value,
            items: [[
                'purchase_id' => $purchase->id,
                'purchase_item_id' => $purchaseItem->id,
                'item_id' => $this->ctx['item']->id,
                'quantity' => $purchaseItem->quantity,
                'unit_cost' => $purchaseItem->unit_price,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => $purchaseItem->batch,
                'expire_date' => $purchaseItem->expire_date?->toDateString(),
            ]],
        ), $overrides))->assertRedirect(route('landed-costs.index'));

        return LandedCost::query()->latest()->firstOrFail();
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
