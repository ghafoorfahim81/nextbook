<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Item;
use App\Services\ItemVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Barcode printing works off variants: SKU, barcode and price live there, so a
 * product with three sizes is three labels, each with its own code.
 */
class BarcodePrintTest extends TestCase
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

    private function makeItem(string $code, array $attributes = []): Item
    {
        return Item::factory()->create(array_merge([
            'branch_id' => $this->ctx['branch']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'code' => $code,
        ], $attributes));
    }

    public function test_the_picker_opens_with_the_ten_newest_items(): void
    {
        // Twelve on top of the one the context already seeds, each a minute
        // apart so "newest" is unambiguous.
        foreach (range(1, 12) as $index) {
            $this->makeItem(
                'BP-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                ['created_at' => now()->addMinutes($index)],
            );
        }

        $this->get(route('item.barcode.print'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventories/Items/BarcodePrint')
                ->has('recentItems.data', 10)
                // Newest first, so the item just created is the one on top.
                ->where('recentItems.data.0.code', 'BP-012')
            );
    }

    public function test_the_seeded_items_carry_their_variants(): void
    {
        $item = $this->makeItem('BP-VAR');
        app(ItemVariantService::class)->ensureDefault($item);

        $this->get(route('item.barcode.print'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // Without the variants the page has no barcode to print at all.
                ->has('recentItems.data.0.variants')
            );
    }

    public function test_search_returns_variants_only_when_they_are_asked_for(): void
    {
        $item = $this->makeItem('BP-SEARCH', ['name' => 'Printable Shirt']);
        app(ItemVariantService::class)->ensureDefault($item);

        $lean = $this->getJson('/search/items?search=Printable&limit=10&fields[]=name')
            ->assertOk()
            ->json('data.0');

        // The sales and purchase pickers keep the lighter payload they had.
        $this->assertArrayNotHasKey('variants', $lean);

        $full = $this->getJson('/search/items?search=Printable&limit=10&fields[]=name&with_variants=1')
            ->assertOk()
            ->json('data.0');

        $this->assertCount(1, $full['variants']);
        $this->assertArrayHasKey('barcode', $full['variants'][0]);
        $this->assertArrayHasKey('sale_price', $full['variants'][0]);
    }

    public function test_a_variant_barcode_finds_its_item(): void
    {
        $item = $this->makeItem('BP-SCAN', ['name' => 'Scanned Product', 'barcode' => null]);

        $item->variants()->create([
            'branch_id' => $item->branch_id,
            'barcode' => 'VAR-BARCODE-9911',
            'sku' => 'VAR-SKU-9911',
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Scanning the code printed on the shelf has to find the product, and the
        // item's own columns cannot answer that.
        $found = $this->getJson('/search/items?search=VAR-BARCODE-9911&limit=10&fields[]=name&fields[]=barcode&with_variants=1')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $found);
        $this->assertSame('BP-SCAN', $found[0]['code']);
    }
}
