<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\ItemVariant;
use App\Services\ItemVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * What the item picker hands a transaction form to price a line with.
 *
 * A sale charges sale_price and a purchase pays purchase_price. avg_cost is
 * neither — it is what the stock on the shelf is worth — so a form that fills
 * its price box from it invoices the customer at cost.
 */
class ItemSearchPricingTest extends TestCase
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

    /** The default variant the app itself creates for every item. */
    private function variant(array $prices): ItemVariant
    {
        $variant = app(ItemVariantService::class)->ensureDefault($this->ctx['item']);
        $variant->update($prices);

        return $variant;
    }

    private function searchPayload(): array
    {
        $response = $this->getJson(route('search.items-list', [
            'warehouse_id' => $this->ctx['warehouse']->id,
            'limit' => 50,
            // Pricing is a property of the item, not of having stock of it.
            'in_stock_only' => false,
        ]))->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('id', $this->ctx['item']->id);

        $this->assertNotNull($row, 'the search did not return the context item');

        return $row;
    }

    public function test_a_variant_carries_its_sale_price_so_a_sale_form_can_fill_it(): void
    {
        $variant = $this->variant(['sale_price' => 250, 'purchase_price' => 90, 'avg_cost' => 80]);

        $payload = $this->searchPayload();
        $row = collect($payload['item_variants'])->firstWhere('id', $variant->id);

        $this->assertNotNull($row);
        // All three, kept distinct — a form picks the one its direction needs.
        $this->assertEquals(250.0, $row['sale_price']);
        $this->assertEquals(90.0, $row['purchase_price']);
        $this->assertEquals(80.0, $row['avg_cost']);
    }

    public function test_an_unpriced_variant_reports_null_so_the_form_falls_back_to_the_item(): void
    {
        $variant = $this->variant(['sale_price' => null, 'purchase_price' => 0, 'avg_cost' => 0]);

        $payload = $this->searchPayload();
        $row = collect($payload['item_variants'])->firstWhere('id', $variant->id);

        // Null, never zero: a false 0 would fill the price box with nothing to
        // sell for, where null tells the form to use the item's own price.
        $this->assertNull($row['sale_price']);
        $this->assertNull($row['purchase_price']);
        $this->assertNull($row['avg_cost']);
    }

    public function test_the_item_itself_still_carries_both_prices(): void
    {
        $this->ctx['item']->update(['sale_price' => 500, 'purchase_price' => 300]);

        $payload = $this->searchPayload();

        $this->assertEquals(500.0, (float) $payload['sale_price']);
        $this->assertEquals(300.0, (float) $payload['purchase_price']);
    }
}
