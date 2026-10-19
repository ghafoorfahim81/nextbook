<?php

namespace Tests\Feature\Ledger;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Ledger\Ledger;
use App\Services\Accounting\LedgerLinkService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The combined view on a paired party's page.
 *
 * "How much does Ahmad actually owe us" used to mean reading two statements
 * and doing the subtraction by hand. These are the four numbers that answer
 * it, and the one that matters most is `offsettable` — set off more than the
 * overlap and the settlement engine invents an advance nobody agreed to.
 */
class CombinedPositionTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Ledger $customer;

    private Ledger $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        $this->customer = $this->ctx['customer_ledger'];
        $this->supplier = $this->ctx['supplier_ledger'];

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 500,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);
    }

    private function sellOnLoan(float $total): void
    {
        $this->post(route('sales.store'), [
            'number' => random_int(1000, 1999),
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 20,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'))->assertSessionHasNoErrors();
    }

    private function buyOnLoan(float $total): void
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(2000, 2999),
            'supplier_id' => $this->supplier->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function position(Ledger $from): ?array
    {
        return app(LedgerLinkService::class)->combinedPosition($from->fresh());
    }

    public function test_an_unpaired_account_has_no_combined_view(): void
    {
        $this->sellOnLoan(5000);

        $this->assertNull(
            $this->position($this->customer),
            'An account with no pairing must not claim a combined position.',
        );
    }

    public function test_it_reports_both_sides_the_overlap_and_the_net(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);
        app(LedgerLinkService::class)->link($this->customer, $this->supplier);

        $position = $this->position($this->customer);

        $this->assertEqualsWithDelta(5000.0, $position['receivable'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $position['payable'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $position['offsettable'], 0.01, 'Only the overlap can be set off.');
        $this->assertEqualsWithDelta(2000.0, $position['net'], 0.01);
        $this->assertSame('receivable', $position['net_side']);
    }

    public function test_it_reads_the_same_from_either_side(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);
        app(LedgerLinkService::class)->link($this->customer, $this->supplier);

        $this->assertEquals(
            $this->position($this->customer),
            $this->position($this->supplier),
            'The combined position is a property of the pair, not of the account it is read from.',
        );
    }

    public function test_the_net_flips_when_we_owe_them_more(): void
    {
        $this->sellOnLoan(1000);
        $this->buyOnLoan(4000);
        app(LedgerLinkService::class)->link($this->customer, $this->supplier);

        $position = $this->position($this->customer);

        $this->assertEqualsWithDelta(1000.0, $position['offsettable'], 0.01);
        $this->assertEqualsWithDelta(3000.0, $position['net'], 0.01);
        $this->assertSame('payable', $position['net_side']);
    }

    public function test_nothing_overlaps_when_one_side_is_clear(): void
    {
        $this->sellOnLoan(5000);
        app(LedgerLinkService::class)->link($this->customer, $this->supplier);

        $position = $this->position($this->customer);

        $this->assertEqualsWithDelta(5000.0, $position['receivable'], 0.01);
        $this->assertEqualsWithDelta(0.0, $position['payable'], 0.01);
        $this->assertEqualsWithDelta(
            0.0,
            $position['offsettable'],
            0.01,
            'With nothing owed the other way there is nothing to set off.',
        );
    }

    public function test_the_party_page_carries_the_combined_position(): void
    {
        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);
        app(LedgerLinkService::class)->link($this->customer, $this->supplier);

        $response = $this->getJson(route('customers.show', $this->customer->id));

        $response->assertOk();
        $this->assertEqualsWithDelta(3000.0, $response->json('combinedPosition.offsettable'), 0.01);
        $this->assertSame(
            $this->supplier->id,
            $response->json('customer.counterpart.id'),
            'The page should name the paired account so it can be linked to.',
        );
    }
}
