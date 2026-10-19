<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The shared document lifecycle on both sides: the screens render, a draft can
 * be posted, a posted document can be reversed, and a draft can be deleted,
 * restored (with its lines) and force-deleted.
 */
class DocumentLifecycleOperationsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        app(ItemVariantService::class)->ensureDefault($this->ctx['item']);
        $this->receive(50);
    }

    private function receive(float $quantity): void
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
            'expire_date' => null,
            'date' => '2026-03-01',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'opening',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    private function draftMode(string $key): void
    {
        $this->ctx['user']->setPreference("transaction.{$key}_post_immediately", false);
        $this->ctx['user']->save();
    }

    private function salePayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 9101,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => 150,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 5,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 30,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ], $overrides);
    }

    private function purchasePayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 9201,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 5,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ], $overrides);
    }

    // ------------------------------------------------------------------ sale

    public function test_the_sale_screens_render(): void
    {
        $this->post(route('sales.store'), $this->salePayload())->assertRedirect();
        $sale = Sale::query()->latest()->firstOrFail();

        $this->get(route('sales.index'))->assertOk();
        $this->get(route('sales.create'))->assertOk();
        $this->get(route('sales.show', $sale))->assertOk();
        $this->get(route('sales.print', $sale))->assertOk();
    }

    public function test_a_draft_sale_can_be_posted_then_reversed(): void
    {
        $this->draftMode('sale');

        $this->post(route('sales.store'), $this->salePayload())->assertRedirect();
        $sale = Sale::query()->latest()->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $sale->status);

        // A draft is still editable.
        $this->get(route('sales.edit', $sale))->assertOk();

        $this->post(route('sales.post', $sale))->assertRedirect();
        $this->assertSame(TransactionStatus::POSTED->value, $sale->fresh()->status);

        $this->post(route('sales.reverse', $sale), ['reason' => 'keyed twice'])->assertRedirect();
        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
    }

    public function test_a_draft_sale_can_be_deleted_restored_and_force_deleted(): void
    {
        $this->draftMode('sale');

        $this->post(route('sales.store'), $this->salePayload())->assertRedirect();
        $sale = Sale::query()->latest()->firstOrFail();

        $this->delete(route('sales.destroy', $sale))->assertRedirect();
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
        $this->assertSoftDeleted('sale_items', ['sale_id' => $sale->id]);

        $this->patch(route('sales.restore', $sale->id))->assertRedirect();
        $this->assertNotSoftDeleted('sales', ['id' => $sale->id]);
        $this->assertNotSoftDeleted('sale_items', ['sale_id' => $sale->id]);

        $this->delete(route('sales.destroy', $sale))->assertRedirect();
        $this->delete(route('sales.force-delete', $sale->id))->assertRedirect();
        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
    }

    // -------------------------------------------------------------- purchase

    public function test_the_purchase_screens_render(): void
    {
        $this->post(route('purchases.store'), $this->purchasePayload())->assertRedirect();
        $purchase = Purchase::query()->latest()->firstOrFail();

        $this->get(route('purchases.index'))->assertOk();
        $this->get(route('purchases.create'))->assertOk();
        $this->get(route('purchases.show', $purchase))->assertOk();
    }

    public function test_a_draft_purchase_can_be_posted_then_reversed(): void
    {
        $this->draftMode('purchase');

        $this->post(route('purchases.store'), $this->purchasePayload())->assertRedirect();
        $purchase = Purchase::query()->latest()->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $purchase->status);

        $this->get(route('purchases.edit', $purchase))->assertOk();

        $this->post(route('purchases.post', $purchase))->assertRedirect();
        $this->assertSame(TransactionStatus::POSTED->value, $purchase->fresh()->status);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'keyed twice'])->assertRedirect();
        $this->assertSame(TransactionStatus::REVERSED->value, $purchase->fresh()->status);
    }

    public function test_a_draft_purchase_can_be_deleted_restored_and_force_deleted(): void
    {
        $this->draftMode('purchase');

        $this->post(route('purchases.store'), $this->purchasePayload())->assertRedirect();
        $purchase = Purchase::query()->latest()->firstOrFail();

        $this->delete(route('purchases.destroy', $purchase))->assertRedirect();
        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertSoftDeleted('purchase_items', ['purchase_id' => $purchase->id]);

        $this->patch(route('purchases.restore', $purchase->id))->assertRedirect();
        $this->assertNotSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertNotSoftDeleted('purchase_items', ['purchase_id' => $purchase->id]);

        $this->delete(route('purchases.destroy', $purchase))->assertRedirect();
        $this->delete(route('purchases.force-delete', $purchase->id))->assertRedirect();
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    }

    /** A reversed document is finished: it must not be reversed a second time. */
    public function test_a_reversed_sale_cannot_be_reversed_again(): void
    {
        $this->post(route('sales.store'), $this->salePayload())->assertRedirect();
        $sale = Sale::query()->latest()->firstOrFail();

        $this->post(route('sales.reverse', $sale), ['reason' => 'first'])->assertRedirect();
        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);

        // Refused at the controller with a 422; only a posted document reverses.
        $this->post(route('sales.reverse', $sale), ['reason' => 'second'])
            ->assertStatus(422);

        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
    }
}
