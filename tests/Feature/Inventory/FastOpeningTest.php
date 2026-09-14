<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockSourceType;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Fast Opening posts the stock a business already holds, for items that have no
 * opening yet. It writes straight to StockService rather than going through the
 * item form, so the branch, the variant and the GL entry all have to be built
 * here — and posting twice must never double the stock.
 */
class FastOpeningTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'item_id' => $this->ctx['item']->id,
            'quantity' => 10,
            'cost' => 25,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
        ], $overrides);
    }

    public function test_it_lists_only_items_without_an_opening(): void
    {
        $this->get(route('item.fast.opening'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Inventories/Items/FastOpening')
                ->has('items.data', 1)
            );
    }

    public function test_it_posts_an_opening_with_stock_and_a_balanced_voucher(): void
    {
        $response = $this->post(route('fast-opening.store'), [
            'items' => [$this->payload()],
        ]);

        $response->assertSessionHasNoErrors();
        // The old store() returned nothing at all, so Inertia never got a
        // redirect back and the page had to reload itself.
        $response->assertRedirect(route('item.fast.opening'));

        $item = $this->ctx['item'];

        $this->assertEqualsWithDelta(
            10.0,
            (float) StockBalance::where('item_id', $item->id)->sum('quantity'),
            0.0001,
        );

        $movement = StockMovement::where('item_id', $item->id)
            ->where('source', StockSourceType::OPENING->value)
            ->firstOrFail();

        // Companies have no branch_id, so the old `auth()->user()->company->branch_id`
        // passed null here and only the HasBranch boot hook saved it.
        $this->assertSame($this->ctx['branch']->id, $movement->branch_id);
        $this->assertSame($item->variants()->first()->id, $movement->variant_id);

        $this->assertTrue(Transaction::where('reference_id', $item->id)->exists());
    }

    public function test_posting_the_same_item_twice_does_not_double_the_stock(): void
    {
        $rows = ['items' => [$this->payload()]];

        $this->post(route('fast-opening.store'), $rows)->assertSessionHasNoErrors();
        // A stale tab, a double click, a back-and-resubmit — all land here.
        $this->post(route('fast-opening.store'), $rows)->assertSessionHasNoErrors();

        $item = $this->ctx['item'];

        $this->assertSame(
            1,
            StockMovement::where('item_id', $item->id)
                ->where('source', StockSourceType::OPENING->value)
                ->count(),
        );

        $this->assertEqualsWithDelta(
            10.0,
            (float) StockBalance::where('item_id', $item->id)->sum('quantity'),
            0.0001,
        );
    }

    public function test_rows_with_no_quantity_are_skipped_rather_than_rejected(): void
    {
        $other = Item::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'code' => 'SKIP-1',
        ]);

        $this->post(route('fast-opening.store'), [
            'items' => [
                // Not counted: no quantity, no cost, no warehouse — and that is fine.
                ['item_id' => $other->id, 'quantity' => '', 'cost' => '', 'unit_measure_id' => $this->ctx['unit_measure']->id, 'warehouse_id' => null],
                $this->payload(),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(StockMovement::where('item_id', $other->id)->exists());
        $this->assertTrue(StockMovement::where('item_id', $this->ctx['item']->id)->exists());
    }

    public function test_a_counted_row_needs_a_warehouse_and_a_cost(): void
    {
        $this->post(route('fast-opening.store'), [
            'items' => [$this->payload(['warehouse_id' => null, 'cost' => 0])],
        ])
            ->assertSessionHasErrors(['items.0.warehouse_id', 'items.0.cost']);

        $this->assertFalse(StockMovement::where('item_id', $this->ctx['item']->id)->exists());
    }

    public function test_it_reports_when_there_is_nothing_counted(): void
    {
        $this->post(route('fast-opening.store'), [
            'items' => [$this->payload(['quantity' => 0, 'cost' => '', 'warehouse_id' => null])],
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $this->assertFalse(StockMovement::where('item_id', $this->ctx['item']->id)->exists());
    }
}
