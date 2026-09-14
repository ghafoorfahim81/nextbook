<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Services\ItemVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Pricing works off variants: price lives there now, and a product with choices
 * has one price per variant. The item's own column stays mirrored from the
 * default variant while sales and the POS still read it.
 */
class ItemPricingTest extends TestCase
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

    public function test_it_lists_ten_rows_by_default(): void
    {
        foreach (range(1, 14) as $index) {
            app(ItemVariantService::class)->ensureDefault(
                $this->makeItem('PR-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT))
            );
        }

        $this->get(route('item-pricing.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventories/Pricing/Index')
                // Opening the screen with the whole catalogue was a slow page
                // nobody asked for.
                ->has('items.data', 10)
                ->where('filters.perPage', 10)
            );
    }

    public function test_a_product_with_variants_gets_one_row_per_variant(): void
    {
        $item = $this->makeItem('PR-VAR', ['name' => 'Variant Shirt']);

        app(ItemVariantService::class)->sync($item, [
            ['attributes' => ['size' => 'S'], 'sku' => 'SH-S', 'sale_price' => 100, 'is_default' => true],
            ['attributes' => ['size' => 'M'], 'sku' => 'SH-M', 'sale_price' => 120],
            ['attributes' => ['size' => 'L'], 'sku' => 'SH-L', 'sale_price' => 140],
        ]);

        $this->get(route('item-pricing.index', ['search' => 'Variant Shirt']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('items.data', 3));
    }

    public function test_a_plain_product_carries_no_variant_label(): void
    {
        $item = $this->makeItem('PR-PLAIN', ['name' => 'Plain Soap']);
        app(ItemVariantService::class)->ensureDefault($item);

        $this->get(route('item-pricing.index', ['search' => 'Plain Soap']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                // Labelling the lone default variant would just be noise.
                ->where('items.data.0.variant_label', '')
                ->where('items.data.0.name', 'Plain Soap')
            );
    }

    public function test_it_saves_many_prices_in_one_request(): void
    {
        $first = $this->makeItem('PR-B1');
        $second = $this->makeItem('PR-B2');

        $firstVariant = app(ItemVariantService::class)->ensureDefault($first);
        $secondVariant = app(ItemVariantService::class)->ensureDefault($second);

        $this->patch(route('item-pricing.bulk-update'), [
            'prices' => [
                ['variant_id' => $firstVariant->id, 'sale_price' => 250],
                ['variant_id' => $secondVariant->id, 'sale_price' => 399.5],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(250.0, (float) $firstVariant->fresh()->sale_price);
        $this->assertSame(399.5, (float) $secondVariant->fresh()->sale_price);
    }

    public function test_the_default_variant_price_mirrors_onto_the_item(): void
    {
        $item = $this->makeItem('PR-MIRROR');
        $variant = app(ItemVariantService::class)->ensureDefault($item);

        $this->patch(route('item-pricing.bulk-update'), [
            'prices' => [['variant_id' => $variant->id, 'sale_price' => 777]],
        ])->assertSessionHasNoErrors();

        // Sales, the POS and barcode labels still read items.sale_price.
        $this->assertSame(777.0, (float) $item->fresh()->sale_price);
    }

    public function test_a_non_default_variant_does_not_touch_the_item_column(): void
    {
        $item = $this->makeItem('PR-NONDEF', ['name' => 'Two Sizes']);

        app(ItemVariantService::class)->sync($item, [
            ['attributes' => ['size' => 'S'], 'sku' => 'TS-S', 'sale_price' => 100, 'is_default' => true],
            ['attributes' => ['size' => 'L'], 'sku' => 'TS-L', 'sale_price' => 200],
        ]);

        $large = ItemVariant::where('sku', 'TS-L')->firstOrFail();

        $this->patch(route('item-pricing.bulk-update'), [
            'prices' => [['variant_id' => $large->id, 'sale_price' => 555]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(555.0, (float) $large->fresh()->sale_price);
        // The item mirrors the default variant, which was not the one repriced.
        $this->assertSame(100.0, (float) $item->fresh()->sale_price);
    }

    public function test_it_rejects_a_negative_price(): void
    {
        $variant = app(ItemVariantService::class)->ensureDefault($this->makeItem('PR-NEG'));

        $this->patch(route('item-pricing.bulk-update'), [
            'prices' => [['variant_id' => $variant->id, 'sale_price' => -5]],
        ])->assertSessionHasErrors('prices.0.sale_price');

        $this->assertNotSame(-5.0, (float) $variant->fresh()->sale_price);
    }

    public function test_search_matches_the_item_and_the_variant(): void
    {
        $item = $this->makeItem('PR-SEARCH', ['name' => 'Searchable Kettle']);

        app(ItemVariantService::class)->sync($item, [
            ['attributes' => ['color' => 'Red'], 'sku' => 'KETTLE-RED', 'sale_price' => 100, 'is_default' => true],
        ]);

        // By the item's name...
        $this->get(route('item-pricing.index', ['search' => 'Searchable']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));

        // ...and by the variant's own SKU.
        $this->get(route('item-pricing.index', ['search' => 'KETTLE-RED']))
            ->assertInertia(fn ($page) => $page->has('items.data', 1));
    }
}
