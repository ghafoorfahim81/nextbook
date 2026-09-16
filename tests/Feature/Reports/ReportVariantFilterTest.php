<?php

namespace Tests\Feature\Reports;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\ItemVariant;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\ReportService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The variant filter on the reports screen.
 *
 * One item with two variants, each traded separately: a report filtered to one
 * variant must show that variant's figures only, and the item-wise reports must
 * name the variant on every row.
 */
class ReportVariantFilterTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private ItemVariant $red;

    private ItemVariant $blue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        $this->red = $this->variant(['colour' => 'Red'], isDefault: true);
        $this->blue = $this->variant(['colour' => 'Blue']);
    }

    private function variant(array $attributes, bool $isDefault = false): ItemVariant
    {
        return ItemVariant::query()->create([
            'item_id' => $this->ctx['item']->id,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
            'attributes' => $attributes,
            'variant_key' => ItemVariant::makeKey($attributes),
            'is_default' => $isDefault,
            'is_active' => true,
            'sort_order' => $isDefault ? 0 : 1,
        ]);
    }

    private function receive(ItemVariant $variant, float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'variant_id' => $variant->id,
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

    private function filters(array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'ledger_id' => null,
            'customer_id' => null,
            'supplier_id' => null,
            'item_id' => $this->ctx['item']->id,
            'variant_id' => null,
            'account_id' => null,
            'warehouse_id' => null,
            'per_page' => 25,
            'page' => 1,
        ], $overrides);
    }

    private function sell(ItemVariant $variant, float $quantity, float $unitPrice, int $number): Sale
    {
        $this->post(route('sales.store'), [
            'number' => $number,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'variant_id' => $variant->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Sale::query()->latest()->firstOrFail();
    }

    private function buy(ItemVariant $variant, float $quantity, float $unitPrice, int $number): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => $number,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-10',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'variant_id' => $variant->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->latest()->firstOrFail();
    }

    public function test_the_sale_item_wise_report_filters_to_one_variant_and_names_it(): void
    {
        $this->receive($this->red, 20, 10);
        $this->receive($this->blue, 20, 10);
        $this->sell($this->red, 3, 100, 6001);
        $this->sell($this->blue, 7, 100, 6002);

        $service = app(ReportService::class);

        $all = $service->getSalesReport($this->filters(['report' => 'sales_report']));
        $this->assertEqualsWithDelta(10.0, (float) $all['summary']['total_quantity'], 0.0001);

        $redOnly = $service->getSalesReport($this->filters([
            'report' => 'sales_report',
            'variant_id' => $this->red->id,
        ]));

        $this->assertEqualsWithDelta(
            3.0,
            (float) $redOnly['summary']['total_quantity'],
            0.0001,
            'Filtering to the Red variant must leave only the Red line.',
        );
        $this->assertCount(1, $redOnly['rows']);
        $this->assertSame('Red', $redOnly['rows'][0]['variant']);
    }

    public function test_the_purchase_item_wise_report_filters_to_one_variant_and_names_it(): void
    {
        $this->buy($this->red, 4, 20, 6101);
        $this->buy($this->blue, 9, 20, 6102);

        $service = app(ReportService::class);

        $all = $service->getPurchaseReport($this->filters(['report' => 'purchase_report']));
        $this->assertEqualsWithDelta(13.0, (float) $all['summary']['total_quantity'], 0.0001);

        $blueOnly = $service->getPurchaseReport($this->filters([
            'report' => 'purchase_report',
            'variant_id' => $this->blue->id,
        ]));

        $this->assertEqualsWithDelta(9.0, (float) $blueOnly['summary']['total_quantity'], 0.0001);
        $this->assertCount(1, $blueOnly['rows']);
        $this->assertSame('Blue', $blueOnly['rows'][0]['variant']);
    }

    public function test_inventory_valuation_counts_only_the_chosen_variant(): void
    {
        $this->receive($this->red, 6, 10);
        $this->receive($this->blue, 14, 10);

        $service = app(ReportService::class);

        $all = $service->getInventoryValuation($this->filters(['report' => 'inventory_valuation']));
        $this->assertEqualsWithDelta(20.0, (float) $all['summary']['total_quantity'], 0.0001);

        $redOnly = $service->getInventoryValuation($this->filters([
            'report' => 'inventory_valuation',
            'variant_id' => $this->red->id,
        ]));

        $this->assertEqualsWithDelta(6.0, (float) $redOnly['summary']['total_quantity'], 0.0001);
    }

    public function test_the_stock_movement_report_counts_only_the_chosen_variant(): void
    {
        $this->receive($this->red, 6, 10);
        $this->receive($this->blue, 14, 10);

        $service = app(ReportService::class);

        $blueOnly = $service->getStockMovements($this->filters([
            'report' => 'stock_movement',
            'variant_id' => $this->blue->id,
        ]));

        $this->assertEqualsWithDelta(14.0, (float) $blueOnly['summary']['total_quantity'], 0.0001);
    }

    /**
     * A variant belongs to one item. Selecting a variant without an item would
     * otherwise let a stale value from a previously chosen item silently empty
     * the report, so it is dropped during normalisation.
     */
    public function test_a_variant_without_an_item_is_ignored(): void
    {
        $this->receive($this->red, 6, 10);
        $this->receive($this->blue, 14, 10);

        $page = app(ReportService::class)->getPageData($this->ctx['user'], [
            'report' => 'inventory_valuation',
            'branch_id' => $this->ctx['branch']->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'item_id' => null,
            'variant_id' => $this->red->id,
        ]);

        $this->assertNull($page['filters']['variant_id']);
    }

    public function test_the_page_ships_variants_tagged_with_their_item(): void
    {
        $page = app(ReportService::class)->getPageData($this->ctx['user'], [
            'report' => 'inventory_valuation',
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $variants = collect($page['filterOptions']['variants']);
        $mine = $variants->where('item_id', $this->ctx['item']->id);

        // The picker narrows by item_id client-side, so every row needs one.
        $this->assertGreaterThanOrEqual(2, $mine->count());
        $this->assertEqualsCanonicalizing(
            ['Red', 'Blue'],
            $mine->whereIn('id', [$this->red->id, $this->blue->id])->pluck('name')->all(),
        );
    }
}
