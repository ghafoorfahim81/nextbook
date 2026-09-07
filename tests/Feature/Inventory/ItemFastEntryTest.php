<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockSourceType;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Fast Entry creates catalogue rows in bulk. It builds the item directly rather
 * than through the item form, so it still has to give each one its default
 * variant — variant_id must never be null downstream.
 */
class ItemFastEntryTest extends TestCase
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

    public function test_it_creates_items_each_with_a_default_variant(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                ['name' => 'Bottled Water 500ml', 'code' => '7001', 'barcode' => '7000001', 'measure_id' => $this->ctx['unit_measure']->id, 'purchase_price' => 8, 'sale_price' => 12],
                ['name' => 'Sponge Cake', 'code' => '7002', 'measure_id' => $this->ctx['unit_measure']->id, 'purchase_price' => 40, 'sale_price' => 60],
            ],
        ])->assertSessionHasNoErrors();

        $water = Item::where('code', '7001')->firstOrFail();
        $this->assertCount(1, $water->variants);

        $variant = $water->variants->first();
        $this->assertTrue($variant->is_default);
        $this->assertSame('7000001', $variant->barcode);
        $this->assertSame('8.0000', (string) $variant->purchase_price);

        // The legacy item columns stay mirrored from the default variant.
        $this->assertSame('7000001', $water->barcode);
        $this->assertSame('12.00', (string) $water->sale_price);

        $this->assertCount(1, Item::where('code', '7002')->firstOrFail()->variants);
    }

    public function test_it_posts_an_opening_when_quantity_and_warehouse_are_given(): void
    {
        $this->post(route('item.fast.store'), [
            'items' => [
                [
                    'name' => 'Cooking Oil 1L',
                    'code' => '7003',
                    'measure_id' => $this->ctx['unit_measure']->id,
                    'purchase_price' => 90,
                    'sale_price' => 110,
                    'quantity' => 10,
                    'warehouse_id' => $this->ctx['warehouse']->id,
                ],
            ],
        ])->assertSessionHasNoErrors();

        $item = Item::where('code', '7003')->firstOrFail();
        $this->assertCount(1, $item->variants);

        $this->assertEqualsWithDelta(
            10.0,
            (float) StockBalance::where('item_id', $item->id)->sum('quantity'),
            0.0001,
        );

        $this->assertTrue(
            StockMovement::where('item_id', $item->id)
                ->where('source', StockSourceType::OPENING->value)
                ->exists()
        );
    }
}
