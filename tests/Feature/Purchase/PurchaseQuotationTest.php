<?php

namespace Tests\Feature\Purchase;

use App\Enums\PurchaseQuotationStatus;
use App\Models\Purchase\PurchaseQuotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class PurchaseQuotationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 8001,
            'date' => '2026-03-19',
            'valid_until' => '2026-04-19',
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'discount' => 0,
            'note' => 'test purchase quotation',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'quantity' => 4,
                    'free' => 0,
                    'unit_price' => 25,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'batch' => null,
                    'color' => null,
                    'expire_date' => null,
                    'size_id' => null,
                    'category_id' => null,
                    'discount' => 0,
                ],
            ],
        ], $overrides);
    }

    public function test_purchase_quotation_can_be_created_as_draft(): void
    {
        $response = $this->post(route('purchase-quotations.store'), $this->storePayload());

        $response->assertRedirect(route('purchase-quotations.index'));

        $this->assertDatabaseHas('purchase_quotations', [
            'number' => 8001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'status' => PurchaseQuotationStatus::DRAFT->value,
        ]);

        $quotation = PurchaseQuotation::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('purchase_quotation_items', [
            'purchase_quotation_id' => $quotation->id,
            'item_id' => $this->ctx['item']->id,
            'quantity' => 4.00,
            'unit_price' => 25.0000,
        ]);
    }

    public function test_index_and_show_render(): void
    {
        $this->post(route('purchase-quotations.store'), $this->storePayload());
        $quotation = PurchaseQuotation::query()->latest()->firstOrFail();

        $this->get(route('purchase-quotations.index'))->assertOk();
        $this->getJson(route('purchase-quotations.show', $quotation->id))
            ->assertOk()
            ->assertJsonPath('data.number', 8001)
            ->assertJsonPath('data.status_label', 'Draft');
    }

    public function test_draft_quotation_can_be_posted_then_not_edited(): void
    {
        $this->post(route('purchase-quotations.store'), $this->storePayload(['number' => 8002]));
        $quotation = PurchaseQuotation::query()->where('number', 8002)->firstOrFail();
        $this->assertEquals(PurchaseQuotationStatus::DRAFT->value, $quotation->status);

        $this->post(route('purchase-quotations.post', $quotation->id))->assertRedirect();
        $this->assertDatabaseHas('purchase_quotations', ['id' => $quotation->id, 'status' => PurchaseQuotationStatus::POSTED->value]);

        $this->get(route('purchase-quotations.edit', $quotation->id))->assertRedirect();
    }

    public function test_quotation_can_be_cancelled(): void
    {
        $this->post(route('purchase-quotations.store'), $this->storePayload(['number' => 8003]));
        $quotation = PurchaseQuotation::query()->where('number', 8003)->firstOrFail();

        $this->post(route('purchase-quotations.cancel', $quotation->id))->assertRedirect();
        $this->assertDatabaseHas('purchase_quotations', ['id' => $quotation->id, 'status' => PurchaseQuotationStatus::CANCELLED->value]);
    }

    public function test_quotation_can_be_printed(): void
    {
        $this->post(route('purchase-quotations.store'), $this->storePayload(['number' => 8004]));
        $quotation = PurchaseQuotation::query()->where('number', 8004)->firstOrFail();

        $this->get(route('purchase-quotations.print', $quotation->id))->assertOk();
    }
}
