<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\Item;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The Item menu's screens and lifecycle operations — the parts ItemCrudTest
 * does not reach: the list/show/edit screens, the in and out record views, and
 * delete / restore / force-delete.
 */
class ItemOperationsTest extends TestCase
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
    }

    private function receive(float $quantity): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 12,
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

    public function test_the_list_create_show_and_edit_screens_render(): void
    {
        $item = $this->ctx['item'];

        $this->get(route('items.index'))->assertOk();
        $this->get(route('items.create'))->assertOk();
        $this->get(route('items.show', $item))->assertOk();
        $this->get(route('items.edit', $item))->assertOk();
    }

    public function test_the_in_and_out_record_views_render(): void
    {
        $this->receive(10);

        $this->get(route('items.in-records', $this->ctx['item']))->assertOk();
        $this->get(route('items.out-records', $this->ctx['item']))->assertOk();
    }

    public function test_an_item_can_be_toggled_inactive_and_back(): void
    {
        $item = $this->ctx['item'];

        $this->patch(route('items.toggle-active', $item))->assertRedirect();
        $this->assertFalse((bool) $item->fresh()->is_active);

        $this->patch(route('items.toggle-active', $item))->assertRedirect();
        $this->assertTrue((bool) $item->fresh()->is_active);
    }

    public function test_an_item_can_be_deleted_restored_and_force_deleted(): void
    {
        // A fresh item with no posted stock, so deletion is allowed.
        $this->post(route('items.store'), [
            'name' => 'Disposable Widget',
            'code' => '9501',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['sales-revenue']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 50,
            'purchase_price' => 30,
            'variants' => [[
                'attributes' => [],
                'sku' => 'DW-001',
                'sale_price' => 50,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]],
        ])->assertRedirect();

        $item = Item::query()->where('code', '9501')->firstOrFail();

        $this->delete(route('items.destroy', $item))->assertRedirect();
        $this->assertSoftDeleted('items', ['id' => $item->id]);

        $this->patch(route('items.restore', $item->id))->assertRedirect();
        $this->assertNotSoftDeleted('items', ['id' => $item->id]);

        // The default variant has to survive the round trip, or the item comes
        // back with nothing sellable attached to it.
        $this->assertNotSoftDeleted('item_variants', ['item_id' => $item->id]);

        $this->delete(route('items.destroy', $item))->assertRedirect();
        $this->delete(route('items.force-delete', $item->id))->assertRedirect();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }
}
