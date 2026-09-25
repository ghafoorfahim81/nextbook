<?php

namespace Tests\Feature\Sales;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockMovement;
use App\Models\Sale\Sale;
use App\Models\Transaction\Transaction;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A sale of a variant is costed at THAT variant's average.
 *
 * Variants of one item can be worth very different amounts — a 16 GB laptop
 * costs more than the 8 GB beside it. Relieving inventory at the parent item's
 * blended average understates COGS on the expensive variant and overstates it
 * on the cheap one, and since the same figure credits the inventory account,
 * the balance sheet drifts from the stock it is supposed to represent.
 */
class SaleVariantCostingTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Item $item;

    private ItemVariant $cheapVariant;

    private ItemVariant $pricyVariant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        $this->post(route('items.store'), [
            'name' => 'Dell Latitude 5540',
            'code' => '9001',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 900,
            'purchase_price' => 700,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'variants' => [
                ['attributes' => ['ram' => '8 GB'], 'sku' => 'DL-8', 'purchase_price' => 700, 'is_default' => true, 'sort_order' => 0],
                ['attributes' => ['ram' => '16 GB'], 'sku' => 'DL-16', 'purchase_price' => 900, 'sort_order' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->item = Item::where('code', '9001')->firstOrFail();
        $bySku = $this->item->variants->keyBy('sku');
        $this->cheapVariant = $bySku['DL-8'];
        $this->pricyVariant = $bySku['DL-16'];
    }

    private function useWeightedAverage(): void
    {
        $this->item->update(['costing_method' => CostingMethod::WEIGHTED_AVERAGE->value]);
    }

    /** Receive stock against one variant at a known cost. */
    private function receive(ItemVariant $variant, float $quantity, float $unitCost): void
    {
        app(StockService::class)->post([
            'item_id' => $this->item->id,
            'variant_id' => $variant->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    private function sell(?ItemVariant $variant, float $quantity, float $unitPrice = 1500): Sale
    {
        $line = [
            'item_id' => $this->item->id,
            'batch' => null,
            'expire_date' => null,
            'quantity' => $quantity,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'unit_price' => $unitPrice,
            'item_discount' => 0,
            'free' => 0,
            'tax' => 0,
        ];

        if ($variant) {
            $line['variant_id'] = $variant->id;
        }

        $this->post(route('sales.store'), [
            'number' => random_int(7000, 9999),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'variant costing test',
            'item_list' => [$line],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->latest()->firstOrFail();
    }

    /** Total debited to the COGS account across the whole sale's voucher. */
    private function cogsFor(Sale $sale): float
    {
        $transactionId = Transaction::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->value('id');

        return (float) DB::table('transaction_lines')
            ->where('transaction_id', $transactionId)
            ->where('account_id', $this->ctx['accounts']['cost-of-goods-sold']->id)
            ->sum('debit');
    }

    /** Total credited to the inventory account across the whole sale's voucher. */
    private function inventoryCreditFor(Sale $sale): float
    {
        $transactionId = Transaction::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->value('id');

        return (float) DB::table('transaction_lines')
            ->where('transaction_id', $transactionId)
            ->where('account_id', $this->ctx['accounts']['inventory-stock']->id)
            ->sum('credit');
    }

    public function test_cogs_uses_the_selected_variants_average_not_the_parent_items(): void
    {
        $this->useWeightedAverage();

        // 10 cheap at 700 and 10 pricy at 1,100. The parent item's blended
        // average is 900 — the figure the sale used to pick up for either one.
        $this->receive($this->cheapVariant, 10, 700);
        $this->receive($this->pricyVariant, 10, 1100);

        $this->assertEqualsWithDelta(900.0, (float) $this->item->fresh()->avg_cost, 0.01);

        $sale = $this->sell($this->pricyVariant, 2);

        // 2 x 1,100 — the pricy variant's own average, not 2 x 900.
        $this->assertEqualsWithDelta(2200.0, $this->cogsFor($sale), 0.01);
        $this->assertEqualsWithDelta(2200.0, $this->inventoryCreditFor($sale), 0.01);
    }

    public function test_the_line_records_the_variant_cost_it_was_sold_at(): void
    {
        $this->useWeightedAverage();

        $this->receive($this->cheapVariant, 10, 700);
        $this->receive($this->pricyVariant, 10, 1100);

        $sale = $this->sell($this->cheapVariant, 3);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'variant_id' => $this->cheapVariant->id,
            'net_unit_cost' => 700.0000,
        ]);
    }

    public function test_the_stock_movement_is_costed_at_the_variant_average(): void
    {
        $this->useWeightedAverage();

        $this->receive($this->cheapVariant, 10, 700);
        $this->receive($this->pricyVariant, 10, 1100);

        $sale = $this->sell($this->pricyVariant, 2);

        $movement = StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('movement_type', StockMovementType::OUT->value)
            ->firstOrFail();

        $this->assertSame($this->pricyVariant->id, $movement->variant_id);
        $this->assertEqualsWithDelta(1100.0, (float) $movement->unit_cost, 0.01);
    }

    public function test_a_variant_with_no_average_of_its_own_falls_back_to_the_item(): void
    {
        $this->useWeightedAverage();

        $this->receive($this->cheapVariant, 10, 700);
        $this->receive($this->pricyVariant, 10, 1100);

        // Stock on the shelf, but no variant average behind it — the state of
        // any variant whose receipts predate the avg_cost column. Costing the
        // sale at zero would book the whole thing as profit, so the item's
        // blended figure (900) stands in.
        DB::table('item_variants')->where('id', $this->pricyVariant->id)->update(['avg_cost' => 0]);

        $this->assertEqualsWithDelta(0.0, (float) $this->pricyVariant->fresh()->avg_cost, 0.01);

        $sale = $this->sell($this->pricyVariant, 1);

        $this->assertEqualsWithDelta(900.0, $this->cogsFor($sale), 0.01);
    }

    public function test_under_fifo_the_layers_peeked_are_the_selected_variants(): void
    {
        $this->item->update(['costing_method' => CostingMethod::FIFO->value]);

        // Cheap variant received first and cheapest. Under a variant-blind peek
        // the pricy variant's sale would be costed from this 700 layer.
        $this->receive($this->cheapVariant, 10, 700);
        $this->receive($this->pricyVariant, 10, 1100);

        $sale = $this->sell($this->pricyVariant, 2);

        $this->assertEqualsWithDelta(2200.0, $this->cogsFor($sale), 0.01);
        $this->assertEqualsWithDelta(2200.0, $this->inventoryCreditFor($sale), 0.01);
    }

    /**
     * Opening 10 at 50, sell 5, buy 5 at 55, then reverse the sale.
     *
     * The running average the sale leaves behind is 52.50, because the purchase
     * blended against the 5 the sale had taken off the shelf. Undoing the sale
     * makes that history untrue: the receipts that actually stand are 10 at 50
     * and 5 at 55, so both the item and its variant must read
     * (500 + 275) / 15 = 51.6667.
     */
    public function test_reversing_a_sale_re_derives_the_item_average_as_well_as_the_variants(): void
    {
        $this->useWeightedAverage();

        $this->receive($this->cheapVariant, 10, 50);
        $this->assertEqualsWithDelta(50.0, (float) $this->item->fresh()->avg_cost, 0.0001);

        $sale = $this->sell($this->cheapVariant, 5);

        $this->receive($this->cheapVariant, 5, 55);

        // The running figure both tables carry while the sale still stands.
        $this->assertEqualsWithDelta(52.5, (float) $this->item->fresh()->avg_cost, 0.0001);

        $this->post(route('sales.reverse', $sale), ['reason' => 'customer cancelled'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(
            51.6667,
            (float) $this->cheapVariant->fresh()->avg_cost,
            0.001,
            'The variant average already replayed correctly.',
        );
        $this->assertEqualsWithDelta(
            51.6667,
            (float) $this->item->fresh()->avg_cost,
            0.001,
            'The item average must agree with the variant it is made of.',
        );
    }

    public function test_each_variant_on_one_sale_is_costed_separately(): void
    {
        $this->useWeightedAverage();

        $this->receive($this->cheapVariant, 10, 700);
        $this->receive($this->pricyVariant, 10, 1100);

        $this->post(route('sales.store'), [
            'number' => 7777,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 6000,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'both variants',
            'item_list' => [
                [
                    'item_id' => $this->item->id,
                    'variant_id' => $this->cheapVariant->id,
                    'batch' => null, 'expire_date' => null,
                    'quantity' => 2,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 1500, 'item_discount' => 0, 'free' => 0, 'tax' => 0,
                ],
                [
                    'item_id' => $this->item->id,
                    'variant_id' => $this->pricyVariant->id,
                    'batch' => null, 'expire_date' => null,
                    'quantity' => 2,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 1500, 'item_discount' => 0, 'free' => 0, 'tax' => 0,
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::query()->latest()->firstOrFail();

        // (2 x 700) + (2 x 1,100). A variant-blind costing would give 4 x 900.
        $this->assertEqualsWithDelta(3600.0, $this->cogsFor($sale), 0.01);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'variant_id' => $this->cheapVariant->id,
            'net_unit_cost' => 700.0000,
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'variant_id' => $this->pricyVariant->id,
            'net_unit_cost' => 1100.0000,
        ]);
    }
}
