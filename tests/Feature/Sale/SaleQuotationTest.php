<?php

namespace Tests\Feature\Sale;

use App\Enums\SaleQuotationStatus;
use App\Models\Sale\SaleQuotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class SaleQuotationTest extends TestCase
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
            'number' => 7001,
            'date' => '2026-03-19',
            'valid_until' => '2026-04-19',
            'customer_id' => $this->ctx['customer_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'discount' => 0,
            'note' => 'test sale quotation',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'quantity' => 3,
                    'free' => 0,
                    'unit_price' => 50,
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

    public function test_sale_quotation_can_be_created_as_draft(): void
    {
        $response = $this->post(route('sale-quotations.store'), $this->storePayload());

        $response->assertRedirect(route('sale-quotations.index'));

        $this->assertDatabaseHas('sale_quotations', [
            'number' => 7001,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'status' => SaleQuotationStatus::DRAFT->value,
        ]);

        $quotation = SaleQuotation::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('sale_quotation_items', [
            'sale_quotation_id' => $quotation->id,
            'item_id' => $this->ctx['item']->id,
            'quantity' => 3.00,
            'unit_price' => 50.0000,
        ]);
    }

    public function test_index_and_show_render(): void
    {
        $this->post(route('sale-quotations.store'), $this->storePayload());
        $quotation = SaleQuotation::query()->latest()->firstOrFail();

        $this->get(route('sale-quotations.index'))->assertOk();
        $this->getJson(route('sale-quotations.show', $quotation->id))
            ->assertOk()
            ->assertJsonPath('data.number', 7001)
            ->assertJsonPath('data.status_label', 'Draft');
    }

    public function test_draft_quotation_can_be_posted_then_not_edited(): void
    {
        $this->post(route('sale-quotations.store'), $this->storePayload(['number' => 7002]));
        $quotation = SaleQuotation::query()->where('number', 7002)->firstOrFail();
        $this->assertEquals(SaleQuotationStatus::DRAFT->value, $quotation->status);

        $this->post(route('sale-quotations.post', $quotation->id))->assertRedirect();
        $this->assertDatabaseHas('sale_quotations', ['id' => $quotation->id, 'status' => SaleQuotationStatus::POSTED->value]);

        $this->get(route('sale-quotations.edit', $quotation->id))->assertRedirect();
    }

    public function test_quotation_can_be_cancelled(): void
    {
        $this->post(route('sale-quotations.store'), $this->storePayload(['number' => 7003]));
        $quotation = SaleQuotation::query()->where('number', 7003)->firstOrFail();

        $this->post(route('sale-quotations.cancel', $quotation->id))->assertRedirect();
        $this->assertDatabaseHas('sale_quotations', ['id' => $quotation->id, 'status' => SaleQuotationStatus::CANCELLED->value]);
    }

    public function test_quotation_can_be_printed(): void
    {
        $this->post(route('sale-quotations.store'), $this->storePayload(['number' => 7004]));
        $quotation = SaleQuotation::query()->where('number', 7004)->firstOrFail();

        $this->get(route('sale-quotations.print', $quotation->id))->assertOk();
    }
}
